<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../admin/dbcon.php';
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['id'];

// Get companies the user has applied to (for starting new chats)
$applied_q = mysqli_query($con, "SELECT DISTINCT c.id, c.company_name, c.logo 
    FROM job_applications ja 
    JOIN company_jobs cj ON ja.job_id = cj.id 
    JOIN companies c ON cj.company_id = c.id 
    WHERE ja.user_id = $user_id");
$companies = [];
while ($row = mysqli_fetch_assoc($applied_q)) {
    $companies[] = $row;
}

// Also add companies from messages
$msg_companies = mysqli_query($con, "SELECT DISTINCT 
    CASE WHEN sender_type = 'company' THEN sender_id ELSE receiver_id END as cid
    FROM messages 
    WHERE (sender_type = 'user' AND sender_id = $user_id) OR (receiver_type = 'user' AND receiver_id = $user_id)");
while ($row = mysqli_fetch_assoc($msg_companies)) {
    $cid = intval($row['cid']);
    if ($cid > 0) {
        $exists = false;
        foreach ($companies as $c) { if ($c['id'] == $cid) { $exists = true; break; } }
        if (!$exists) {
            $cq = mysqli_query($con, "SELECT id, company_name, logo FROM companies WHERE id = $cid");
            if ($cq && mysqli_num_rows($cq) > 0) {
                $companies[] = mysqli_fetch_assoc($cq);
            }
        }
    }
}
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap');

    :root {
        --lc-grad: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #38bdf8 100%);
        --lc-bubble: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        --lc-accent: #2563eb;
        --lc-border: #eef1f6;
    }
    html, body { overflow: hidden; }
    body { font-family: 'Inter', sans-serif; }
    .lc-head-font { font-family: 'Plus Jakarta Sans', sans-serif; }

    /* ── Wrapper ─────────────────────────────── */
    .lc-wrap { height: calc(100vh - 96px); padding: 18px 20px 20px; max-width: 1240px; margin: 0 auto; }
    .lc-card {
        display: flex;
        height: 100%;
        background: var(--bg-card);
        border: 1px solid var(--lc-border);
        border-radius: 20px;
        box-shadow: 0 18px 48px -18px rgba(15, 23, 42, 0.18);
        overflow: hidden;
    }

    /* ── Sidebar ─────────────────────────────── */
    .lc-sb { width: 330px; border-right: 1px solid var(--lc-border); display: flex; flex-direction: column; flex-shrink: 0; background: var(--bg-card); }
    .lc-sb-head { padding: 20px 20px 14px; }
    .lc-sb-title { display: flex; align-items: center; gap: 10px; }
    .lc-logo-mini {
        width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
        background: var(--lc-grad);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1rem;
        box-shadow: 0 6px 16px -6px rgba(37, 99, 235, 0.5);
    }
    .lc-sb-title h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.15rem; margin: 0; color: var(--text); letter-spacing: -.01em; }
    .lc-sb-title p { font-size: 0.74rem; color: var(--text-muted); margin: 2px 0 0; }
    .lc-sb-search { padding: 10px 20px 6px; }
    .lc-search-box {
        display: flex; align-items: center; gap: 9px;
        background: var(--bg-hover);
        border: 1px solid var(--lc-border);
        border-radius: 12px;
        padding: 9px 13px;
        transition: border-color .2s, background .2s;
    }
    .lc-search-box:focus-within { border-color: var(--lc-accent); background: var(--bg-card); }
    .lc-search-box i { color: var(--text-light); font-size: .85rem; }
    .lc-search-box input { border: none; outline: none; background: transparent; width: 100%; font-size: .86rem; color: var(--text); }
    .lc-search-box input::placeholder { color: var(--text-light); }
    .lc-new-chat { margin: 12px 20px; padding: 11px; border: none; border-radius: 12px; background: var(--lc-grad); color: #fff; font-weight: 700; font-size: .85rem; cursor: pointer; transition: transform .2s, box-shadow .2s; text-align: center; box-shadow: 0 8px 20px -8px rgba(37, 99, 235, .5); }
    .lc-new-chat:hover { transform: translateY(-1px); box-shadow: 0 12px 24px -8px rgba(37, 99, 235, .6); }

    .lc-sb-label { padding: 10px 22px 6px; font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-light); }
    .lc-conv-list { flex: 1; overflow-y: auto; padding: 0 10px 12px; }
    .lc-conv-item {
        display: flex; align-items: center; gap: 12px; padding: 11px 12px;
        cursor: pointer; border-radius: 14px; margin-bottom: 3px;
        transition: background .18s;
    }
    .lc-conv-item:hover { background: var(--bg-hover); }
    .lc-conv-item.active { background: rgba(37, 99, 235, .08); }
    .lc-avatar {
        width: 44px; height: 44px; border-radius: 13px; flex-shrink: 0;
        background: var(--lc-grad);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 1rem; position: relative;
        overflow: hidden;
    }
    .lc-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 13px; }
    .lc-conv-info { flex: 1; min-width: 0; }
    .lc-conv-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .lc-conv-name { font-weight: 700; font-size: .88rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lc-conv-time { font-size: .68rem; color: var(--text-light); flex-shrink: 0; }
    .lc-conv-unread {
        background: var(--lc-grad); color: #fff; font-size: .64rem; font-weight: 700;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 10px -3px rgba(37, 99, 235, .6);
    }

    /* ── Chat Main ───────────────────────────── */
    .lc-main { flex: 1; display: flex; flex-direction: column; min-width: 0; background: var(--bg-card); }
    .lc-chat-head { padding: 14px 24px; border-bottom: 1px solid var(--lc-border); display: flex; align-items: center; gap: 13px; background: var(--bg-card); }
    .lc-status { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #059669; }
    .lc-status .dot { width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, .15); }

    .lc-messages { flex: 1; overflow-y: auto; padding: 26px 26px 14px; display: flex; flex-direction: column; gap: 14px; background: var(--bg); }
    .lc-date-divider { align-self: center; font-size: .68rem; font-weight: 600; color: var(--text-light); background: var(--bg-hover); border: 1px solid var(--lc-border); padding: 4px 12px; border-radius: 999px; }
    .lc-msg { max-width: 68%; display: flex; flex-direction: column; animation: lcMsgIn .22s ease; }
    @keyframes lcMsgIn { from { transform: translateY(6px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .lc-msg.sent { align-self: flex-end; align-items: flex-end; }
    .lc-msg.recv { align-self: flex-start; align-items: flex-start; }
    .lc-bubble { padding: 11px 16px; border-radius: 16px; font-size: .9rem; line-height: 1.55; word-break: break-word; position: relative; }
    .lc-msg.sent .lc-bubble { background: var(--lc-bubble); color: #fff; border-bottom-right-radius: 6px; box-shadow: 0 8px 20px -10px rgba(37, 99, 235, .55); }
    .lc-msg.recv .lc-bubble { background: var(--bg-card); color: var(--text); border-bottom-left-radius: 6px; border: 1px solid var(--lc-border); box-shadow: 0 2px 8px rgba(15, 23, 42, .04); }
    .lc-time { font-size: .64rem; color: var(--text-light); margin-top: 5px; display: inline-flex; align-items: center; gap: 4px; }
    .lc-msg.sent .lc-time { align-self: flex-end; }
    .lc-typing { display: none; align-self: flex-start; align-items: center; gap: 5px; padding: 12px 18px; background: var(--bg-card); border: 1px solid var(--lc-border); border-radius: 16px; border-bottom-left-radius: 6px; box-shadow: 0 2px 8px rgba(15, 23, 42, .04); }
    .lc-typing.show { display: flex; }
    .lc-typing-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--text-light); animation: lcTyping 1.4s infinite ease-in-out; }
    .lc-typing-dot:nth-child(2) { animation-delay: .2s; }
    .lc-typing-dot:nth-child(3) { animation-delay: .4s; }
    @keyframes lcTyping { 0%, 80%, 100% { transform: scale(.8); opacity: .5; } 40% { transform: scale(1.2); opacity: 1; } }

    /* ── Compose ─────────────────────────────── */
    .lc-compose { padding: 14px 20px; border-top: 1px solid var(--lc-border); background: var(--bg-card); display: flex; gap: 12px; align-items: flex-end; }
    .lc-compose-box { flex: 1; display: flex; align-items: flex-end; gap: 10px; background: var(--bg-hover); border: 1px solid var(--lc-border); border-radius: 14px; padding: 6px 8px 6px 14px; transition: border-color .2s, background .2s; }
    .lc-compose-box:focus-within { border-color: var(--lc-accent); background: var(--bg-card); }
    .lc-input { flex: 1; border: none; outline: none; background: transparent; resize: none; font-size: .9rem; font-family: inherit; color: var(--text); min-height: 34px; max-height: 110px; padding: 7px 0; }
    .lc-input::placeholder { color: var(--text-light); }
    .lc-send {
        background: var(--lc-grad); color: #fff; border: none;
        width: 42px; height: 42px; border-radius: 12px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0;
        transition: transform .2s, box-shadow .2s, opacity .2s;
        box-shadow: 0 6px 14px -6px rgba(37, 99, 235, .5);
    }
    .lc-send:hover { transform: scale(1.06); box-shadow: 0 10px 18px -6px rgba(37, 99, 235, .55); }
    .lc-send:disabled { background: var(--border); cursor: not-allowed; transform: none; box-shadow: none; }

    /* ── Empty state ─────────────────────────── */
    .lc-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; background: var(--bg); }
    .lc-empty-ico {
        width: 84px; height: 84px; border-radius: 26px; margin-bottom: 22px;
        background: rgba(37, 99, 235, .08);
        display: flex; align-items: center; justify-content: center; color: var(--lc-accent); font-size: 2.2rem;
    }
    .lc-empty h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.15rem; color: var(--text); margin-bottom: 6px; }
    .lc-empty p { font-size: .86rem; color: var(--text-muted); max-width: 280px; margin: 0; }

    /* ── Modal ───────────────────────────────── */
    .lc-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .55); backdrop-filter: blur(4px); z-index: 1050; align-items: center; justify-content: center; padding: 16px; }
    .lc-overlay.show { display: flex; }
    .lc-modal {
        background: var(--bg-card); border-radius: 22px; width: 100%; max-width: 460px; padding: 28px;
        box-shadow: 0 30px 80px -20px rgba(15, 23, 42, .4);
        animation: lcModalIn .25s ease; max-height: 82vh; overflow-y: auto;
    }
    @keyframes lcModalIn { from { transform: translateY(18px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .lc-modal h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--text); }
    .lc-modal .sub { font-size: .86rem; color: var(--text-muted); margin: 4px 0 20px; }
    .lc-company-pick { display: flex; align-items: center; gap: 13px; padding: 12px 14px; border: 1px solid var(--lc-border); border-radius: 14px; cursor: pointer; transition: all .2s; margin-bottom: 9px; }
    .lc-company-pick:hover { border-color: var(--lc-accent); background: rgba(37, 99, 235, .04); transform: translateX(2px); }
    .lc-company-pick .cp-name { font-weight: 700; font-size: .9rem; color: var(--text); }
    .lc-close-btn { background: var(--bg-hover); border: none; width: 34px; height: 34px; border-radius: 10px; color: var(--text-muted); cursor: pointer; transition: all .2s; }
    .lc-close-btn:hover { background: var(--border); color: var(--text); }

    /* ── Back (mobile) ───────────────────────── */
    .lc-back { display: none; background: var(--bg-hover); border: none; width: 38px; height: 38px; border-radius: 12px; color: var(--text-muted); cursor: pointer; font-size: 1rem; flex-shrink: 0; transition: all .2s; }
    .lc-back:hover { background: var(--border); color: var(--text); }

    /* ── Scrollbars ──────────────────────────── */
    .lc-conv-list::-webkit-scrollbar, .lc-messages::-webkit-scrollbar { width: 6px; }
    .lc-conv-list::-webkit-scrollbar-thumb, .lc-messages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
    .lc-conv-list::-webkit-scrollbar-track, .lc-messages::-webkit-scrollbar-track { background: transparent; }

    /* ── Responsive ──────────────────────────── */
    @media (max-width: 820px) {
        .lc-wrap { padding: 10px; height: calc(100vh - 96px); }
        .lc-card { border-radius: 16px; }
        .lc-sb { width: 100%; border-right: none; }
        .lc-main { display: none; }
        .lc-main.active-mobile { display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1040; border-radius: 0; }
        .lc-back { display: flex; align-items: center; justify-content: center; }
        .lc-msg { max-width: 82%; }
    }
    @media (max-width: 400px) {
        .lc-sb-title p { display: none; }
    }
</style>

<div class="lc-wrap">
    <div class="lc-card">
        <!-- Sidebar -->
        <aside class="lc-sb" id="chatSidebar">
            <div class="lc-sb-head">
                <div class="lc-sb-title">
                    <div class="lc-logo-mini"><i class="fas fa-comments"></i></div>
                    <div>
                        <h3>Messages</h3>
                        <p>Chat with companies in real time</p>
                    </div>
                </div>
            </div>
            <div class="lc-sb-search">
                <div class="lc-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search companies..." id="convSearch" oninput="filterConv(this.value)">
                </div>
            </div>
            <button class="lc-new-chat" onclick="document.getElementById('newChatModal').classList.add('show')">
                <i class="fas fa-plus mr-1"></i> Start New Conversation
            </button>
            <div class="lc-sb-label">Conversations</div>
            <div class="lc-conv-list" id="convList">
                <div class="lc-empty" style="padding: 30px 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 1.6rem;"></i>
                    <p style="margin-top: 12px;">Loading conversations...</p>
                </div>
            </div>
        </aside>

        <!-- Chat Main -->
        <main class="lc-main" id="chatMain">
            <div class="lc-empty" id="chatEmpty">
                <div class="lc-empty-ico"><i class="fas fa-comment-dots"></i></div>
                <h3>Select a conversation</h3>
                <p>Choose a company from the sidebar or start a new chat.</p>
            </div>
            <div id="chatActive" style="display:none; flex:1; flex-direction:column; min-height:0;">
                <div class="lc-chat-head">
                    <button class="lc-back" onclick="goBack()"><i class="fas fa-arrow-left"></i></button>
                    <div class="lc-avatar" id="chatAvatar" style="width:42px; height:42px; font-size:.9rem;"></div>
                    <div style="min-width:0;">
                        <h5 class="lc-head-font" id="chatName" style="font-weight:800; font-size:.98rem; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 40vw;"></h5>
                        <span class="lc-status"><span class="dot"></span><span id="chatStatus">Online</span></span>
                    </div>
                </div>
                <div class="lc-messages" id="chatMessages">
                    <div class="lc-typing" id="typingIndicator">
                        <div class="lc-typing-dot"></div>
                        <div class="lc-typing-dot"></div>
                        <div class="lc-typing-dot"></div>
                    </div>
                </div>
                <div class="lc-compose">
                    <div class="lc-compose-box">
                        <textarea class="lc-input" id="chatInput" placeholder="Type your message..." rows="1" onkeydown="handleKey(event)"></textarea>
                        <button class="lc-send" id="sendBtn" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- New Chat Modal -->
<div class="lc-overlay" id="newChatModal">
    <div class="lc-modal">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h4 class="m-0"><i class="fas fa-plus-circle mr-2" style="color: var(--lc-accent);"></i>New Chat</h4>
            <button class="lc-close-btn" onclick="this.closest('.lc-overlay').classList.remove('show')"><i class="fas fa-times"></i></button>
        </div>
        <p class="sub">Start a conversation with a company</p>
        <?php if (empty($companies)): ?>
            <div class="text-center py-4" style="color: var(--text-muted);">
                <div class="lc-empty-ico" style="width:64px; height:64px; font-size:1.6rem; margin: 0 auto 14px;"><i class="fas fa-building"></i></div>
                <p style="font-size:.88rem;">Apply to jobs first to start chatting with companies.</p>
                <a href="browse_jobs.php" class="lc-new-chat d-inline-block" style="padding: 10px 22px; margin: 14px 0 0;">Browse Jobs</a>
            </div>
        <?php else: ?>
            <?php foreach ($companies as $comp): ?>
                <div class="lc-company-pick" onclick="startChat(<?php echo $comp['id']; ?>, '<?php echo htmlspecialchars(addslashes($comp['company_name'])); ?>', '<?php echo htmlspecialchars($comp['logo'] ?? ''); ?>')">
                    <div class="lc-avatar" style="width:40px; height:40px; font-size:.85rem;">
                        <?php if (!empty($comp['logo']) && file_exists('uploads/company_logos/' . $comp['logo'])): ?>
                            <img src="uploads/company_logos/<?php echo htmlspecialchars($comp['logo']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($comp['company_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <span class="cp-name"><?php echo htmlspecialchars($comp['company_name']); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let activeCompany = null;
let lastMsgId = 0;
let pollTimer = null;

function startChat(companyId, companyName, logo) {
    document.getElementById('newChatModal').classList.remove('show');
    activeCompany = { id: companyId, name: companyName, logo: logo };
    window.LC_ACTIVE_ID = parseInt(companyId, 10);
    lastMsgId = 0;

    document.getElementById('chatEmpty').style.display = 'none';
    var ca = document.getElementById('chatActive');
    ca.style.display = 'flex';

    document.getElementById('chatName').textContent = companyName;
    var av = document.getElementById('chatAvatar');
    if (logo && logo !== '') {
        av.innerHTML = '<img src="uploads/company_logos/' + logo + '" alt="">';
    } else {
        av.innerHTML = companyName.charAt(0).toUpperCase();
    }

    var msgs = document.getElementById('chatMessages');
    msgs.innerHTML = '<div class="lc-typing" id="typingIndicator"><div class="lc-typing-dot"></div><div class="lc-typing-dot"></div><div class="lc-typing-dot"></div></div>';

    document.getElementById('chatInput').focus();
    loadMessages();

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);

    document.querySelectorAll('.lc-conv-item').forEach(el => el.classList.remove('active'));
    var sideItem = document.querySelector('.lc-conv-item[data-id="' + companyId + '"]');
    if (sideItem) sideItem.classList.add('active');

    document.getElementById('chatMain').classList.add('active-mobile');
}

function goBack() {
    document.getElementById('chatMain').classList.remove('active-mobile');
}

function loadMessages() {
    if (!activeCompany) return;
    fetch('../api/chat_poll.php?with_type=company&with_id=' + activeCompany.id + '&since=' + lastMsgId)
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        var msgs = document.getElementById('chatMessages');
        data.messages.forEach(msg => {
            if (document.getElementById('msg-' + msg.id)) return;
            var div = document.createElement('div');
            div.className = 'lc-msg ' + (msg.sender_type === 'user' ? 'sent' : 'recv');
            div.id = 'msg-' + msg.id;
            var time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            div.innerHTML = '<div class="lc-bubble">' + escapeHtml(msg.message) + '</div>' +
                '<span class="lc-time">' + time + (msg.sender_type === 'user' ? (msg.is_read ? ' <i class="fas fa-check-double"></i>' : ' <i class="fas fa-check"></i>') : '') + '</span>';
            msgs.insertBefore(div, document.getElementById('typingIndicator'));
            if (msg.id > lastMsgId) lastMsgId = msg.id;
        });
        msgs.scrollTop = msgs.scrollHeight;
    })
    .catch(err => console.error('Poll error:', err));
}

function sendMessage() {
    if (!activeCompany) return;
    var input = document.getElementById('chatInput');
    var msg = input.value.trim();
    if (!msg) return;

    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    input.value = '';
    input.style.height = 'auto';

    var fd = new FormData();
    fd.append('receiver_type', 'company');
    fd.append('receiver_id', activeCompany.id);
    fd.append('message', msg);

    fetch('../api/chat_send.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            loadMessages();
            loadConversations();
        } else {
            input.value = msg;
        }
    })
    .catch(err => {
        btn.disabled = false;
        input.value = msg;
        console.error('Send error:', err);
    });
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadConversations() {
    fetch('../api/chat_conversations.php')
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        var list = document.getElementById('convList');
        if (data.conversations.length === 0) {
            list.innerHTML = '<div class="lc-empty" style="padding:40px 20px;"><i class="fas fa-comments" style="font-size:1.8rem; opacity:.4;"></i><p style="margin-top:12px; color:var(--text-muted); font-size:.86rem;">No conversations yet</p></div>';
            return;
        }
        var html = '';
        data.conversations.forEach(c => {
            var initial = c.company_name.charAt(0).toUpperCase();
            var active = (activeCompany && activeCompany.id == c.company_id) ? 'active' : '';
            html += '<div class="lc-conv-item ' + active + '" data-id="' + c.company_id + '" data-name="' + c.company_name.toLowerCase() + '" onclick="startChat(' + c.company_id + ', \'' + c.company_name.replace(/'/g, "\\'") + '\', \'' + (c.logo || '') + '\')">';
            html += '<div class="lc-avatar">';
            if (c.logo && c.logo !== '') {
                html += '<img src="uploads/company_logos/' + c.logo + '" alt="">';
            } else {
                html += initial;
            }
            html += '</div>';
            html += '<div class="lc-conv-info"><div class="lc-conv-top"><span class="lc-conv-name">' + escapeHtml(c.company_name) + '</span><span class="lc-conv-time">' + timeAgo(c.last_time) + '</span></div></div>';
            if (c.unread > 0) html += '<span class="lc-conv-unread">' + c.unread + '</span>';
            html += '</div>';
        });
        list.innerHTML = html;
    })
    .catch(err => console.error('Conv load error:', err));
}

function timeAgo(dt) {
    var diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function filterConv(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.lc-conv-item').forEach(el => {
        var name = el.getAttribute('data-name') || '';
        el.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

var chatInput = document.getElementById('chatInput');
chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 110) + 'px';
});

loadConversations();
</script>
</body>
</html>
