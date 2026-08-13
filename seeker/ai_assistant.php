<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/config.php';

$user_id = $_SESSION['id'];

// Load recent chat history
$history = array();
$hq = mysqli_query($con, "SELECT role, message FROM ai_chat_history WHERE user_id = '$user_id' ORDER BY id DESC LIMIT 30");
if ($hq) {
    $rows = array();
    while ($r = mysqli_fetch_assoc($hq)) $rows[] = $r;
    $history = array_reverse($rows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Career Assistant | NovaHire</title>
    <?php require_once __DIR__ . '/../includes/links.php'; ?>
    <?php echo ai_css_link(); ?>
    <style>
        body { background: #f8fafc; }
        .assistant-wrap { max-width: 860px; margin: 0 auto; padding: 30px 16px 40px; }
        .chat-shell {
            background: white; border-radius: 22px; overflow: hidden;
            box-shadow: 0 20px 60px rgba(79,70,229,0.12);
            display: flex; flex-direction: column; height: calc(100vh - 200px);
            min-height: 460px;
        }
        .chat-shell-head {
            background: linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#a855f7 100%);
            color: white; padding: 20px 26px;
            display: flex; align-items: center; gap: 14px;
        }
        .chat-shell-avatar {
            width: 50px; height: 50px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .chat-shell-body {
            flex: 1; overflow-y: auto; padding: 22px 26px;
            background: #f8fafc; display: flex; flex-direction: column; gap: 12px;
        }
        .msg-full { max-width: 80%; padding: 12px 16px; border-radius: 16px; font-size: 0.9rem; line-height: 1.6; word-wrap: break-word; }
        .msg-full.bot { background: white; border: 1px solid #e2e8f0; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-full.user { background: #4f46e5; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-full .ai-quick { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .msg-full .ai-quick button {
            background:#eef2ff; color:#4338ca; border:none; padding:6px 13px;
            border-radius:16px; font-size:0.75rem; font-weight:600; cursor:pointer;
        }
        .chat-shell-foot {
            display: flex; align-items: center; gap: 10px; padding: 16px 26px;
            border-top: 1px solid #e2e8f0; background: white;
        }
        .chat-shell-foot input {
            flex: 1; border: 1px solid #e2e8f0; border-radius: 26px;
            padding: 13px 20px; font-size: 0.9rem; outline: none;
        }
        .chat-shell-foot input:focus { border-color: #4f46e5; }
        .chat-shell-foot button {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(135deg,#4f46e5,#7c3aed);
            color: white; border: none; cursor: pointer; font-size: 1rem;
        }
    </style>
</head>
<body>
<div class="assistant-wrap">
    <div class="chat-shell">
        <div class="chat-shell-head">
            <div class="chat-shell-avatar"><i class="fas fa-robot"></i></div>
            <div>
                <h5 class="m-0 font-weight-bold"><?php echo htmlspecialchars(AI_CHATBOT_NAME); ?></h5>
                <small style="opacity:0.85;"><span class="dot-on" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#34d399;margin-right:5px;"></span><?php echo ai_provider_label(); ?> &bull; online</small>
            </div>
        </div>
        <div class="chat-shell-body" id="aiChatBody">
            <?php if (empty($history)): ?>
                <div class="msg-full bot">
                    Hi! I am your AI career assistant. Ask me anything about NovaHire - jobs, assessments, grooming, interviews or your resume.
                    <div class="ai-quick">
                        <button onclick="aiChatSet('Find jobs for me')">Find jobs</button>
                        <button onclick="aiChatSet('Improve my resume')">Improve resume</button>
                        <button onclick="aiChatSet('Start mock interview')">Mock interview</button>
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
            <input type="text" id="aiChatInput" placeholder="Type your message..." onkeydown="if(event.key==='Enter')aiChatSend()">
            <button onclick="aiChatSend()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/ai/assets/js/chat.js"></script>
<script>
// Override aiChatAddMsg to use the full-page classes
function aiChatAddMsg(role, html) {
    const body = document.getElementById('aiChatBody');
    const div = document.createElement('div');
    div.className = 'msg-full ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = html;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
}
function aiChatTyping(on) {
    const body = document.getElementById('aiChatBody');
    const ex = document.getElementById('aiTyping');
    if (ex) ex.remove();
    if (on) {
        const div = document.createElement('div');
        div.className = 'msg-full bot';
        div.id = 'aiTyping';
        div.innerHTML = '<span class="ai-typing"><span></span><span></span><span></span></span>';
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }
}
function aiChatSet(text) {
    document.getElementById('aiChatInput').value = text;
    aiChatSend();
}
</script>
</body>
</html>
