<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('location: login.php');
    exit();
}
include 'admin/dbcon.php';
include 'includes/functions.php';

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
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Live Chat | NovaHire</title>
    <?php include 'links.php'; ?>
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; overflow: hidden; }
        .chat-wrap { display: flex; height: calc(100vh - 100px); margin: 0 auto; max-width: 1200px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; margin-top: 10px; }

        /* Sidebar */
        .chat-sidebar { width: 320px; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #667eea, #764ba2); }
        .sidebar-header h3 { color: white; font-weight: 800; font-size: 1.2rem; margin: 0; }
        .sidebar-header p { color: rgba(255,255,255,0.8); font-size: 0.8rem; margin: 4px 0 0; }
        .sidebar-search { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
        .sidebar-search input { width: 100%; border: 2px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; }
        .sidebar-search input:focus { border-color: #667eea; }
        .conv-list { flex: 1; overflow-y: auto; }
        .conv-item {
            display: flex; align-items: center; gap: 12px; padding: 14px 16px;
            cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f3f4f6;
        }
        .conv-item:hover { background: #f8fafc; }
        .conv-item.active { background: #eef2ff; border-left: 3px solid #667eea; }
        .conv-avatar {
            width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1rem; position: relative;
        }
        .conv-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
        .conv-info { flex: 1; min-width: 0; }
        .conv-name { font-weight: 700; font-size: 0.9rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-time { font-size: 0.7rem; color: #94a3b8; }
        .conv-unread { background: #667eea; color: white; font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .new-chat-btn { margin: 12px 16px; padding: 10px; border: 2px dashed #667eea; border-radius: 10px; background: transparent; color: #667eea; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; text-align: center; }
        .new-chat-btn:hover { background: #eef2ff; border-style: solid; }

        /* Chat Area */
        .chat-main { flex: 1; display: flex; flex-direction: column; }
        .chat-top { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px; background: white; }
        .chat-top h5 { font-weight: 700; margin: 0; font-size: 1rem; }
        .chat-top small { color: #10b981; font-weight: 600; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 12px; background: #f8fafc; }
        .chat-msg { max-width: 70%; display: flex; flex-direction: column; }
        .chat-msg.sent { align-self: flex-end; }
        .chat-msg.received { align-self: flex-start; }
        .chat-bubble {
            padding: 12px 18px; border-radius: 18px; font-size: 0.9rem;
            line-height: 1.5; word-break: break-word; position: relative;
        }
        .chat-msg.sent .chat-bubble { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-bottom-right-radius: 6px; }
        .chat-msg.received .chat-bubble { background: white; color: #0f172a; border-bottom-left-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .chat-time { font-size: 0.65rem; color: #94a3b8; margin-top: 4px; }
        .chat-msg.sent .chat-time { text-align: right; }
        .typing-indicator { display: none; align-self: flex-start; padding: 10px 18px; background: white; border-radius: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .typing-indicator.show { display: flex; gap: 4px; align-items: center; }
        .typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; animation: typingBounce 1.4s infinite ease-in-out; }
        .typing-dot:nth-child(1) { animation-delay: 0s; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingBounce { 0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; } 40% { transform: scale(1.2); opacity: 1; } }

        /* Compose */
        .chat-compose { padding: 16px 24px; border-top: 1px solid #e5e7eb; background: white; display: flex; gap: 12px; align-items: flex-end; }
        .chat-input {
            flex: 1; border: 2px solid #e5e7eb; border-radius: 14px; padding: 12px 16px;
            font-size: 0.9rem; resize: none; outline: none; min-height: 46px; max-height: 120px;
            font-family: inherit; transition: border-color 0.2s;
        }
        .chat-input:focus { border-color: #667eea; }
        .send-btn {
            background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none;
            width: 46px; height: 46px; border-radius: 14px; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;
        }
        .send-btn:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .send-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Empty state */
        .chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; background: #f8fafc; }
        .chat-empty i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        .chat-empty h3 { font-weight: 700; color: #64748b; margin-bottom: 8px; }

        /* New Chat Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 20px; width: 90%; max-width: 450px; padding: 30px; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .company-pick { display: flex; align-items: center; gap: 12px; padding: 12px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.2s; margin-bottom: 8px; }
        .company-pick:hover { border-color: #667eea; background: #f8fafc; }
        .company-pick .cp-name { font-weight: 700; font-size: 0.9rem; }

        @media (max-width: 768px) {
            .chat-sidebar { width: 100%; }
            .chat-main { display: none; }
            .chat-main.active-mobile { display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: white; border-radius: 0; }
            .back-btn { display: block !important; }
        }
        .back-btn { display: none; color: #667eea; cursor: pointer; font-size: 1.2rem; margin-right: 8px; }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg glass-nav fixed-top">
  <div class="container-fluid px-5 custom-nav-container">
      <a class="navbar-brand d-flex align-items-center" href="seeker_dashboard.php">
        <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
        <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
      </a>
      <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
        <span class="fas fa-bars fa-lg text-dark"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-between" id="collapsibleNavbar">
        <ul class="navbar-nav mx-auto center-menu">
           <li class="nav-item"><a class="nav-link" href="seeker_dashboard.php">Home</a></li>
           <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
           <li class="nav-item"><a class="nav-link" href="browse_jobs.php">Browse Jobs</a></li>
           <li class="nav-item"><a class="nav-link" href="my_application.php">Applications</a></li>
           <li class="nav-item"><a class="nav-link" href="message_center.php">Messages</a></li>
           <li class="nav-item"><a class="nav-link" href="live_chat.php" style="font-weight:700; color:#667eea;">Live Chat</a></li>
        </ul>
        <ul class="navbar-nav align-items-center right-menu">
            <li class="nav-item dropdown">
                <a class="nav-link user-pill dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                   <div class="user-avatar-sm"><i class="fas fa-user"></i></div>
                   <span class="user-name-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 user-menu-dropdown">
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle mr-2 text-muted"></i> My Profile</a>
                    <a class="dropdown-item" href="my_application.php"><i class="fas fa-file-alt mr-2 text-muted"></i> Applications</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
      </div>
  </div>
</nav>

<div class="container" style="height: calc(100vh - 100px); padding-top: 10px;">
<div class="chat-wrap">
    <!-- Sidebar -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-comments mr-2"></i>Live Chat</h3>
            <p>Real-time conversations with companies</p>
        </div>
        <div class="sidebar-search">
            <input type="text" placeholder="Search companies..." id="convSearch" oninput="filterConv(this.value)">
        </div>
        <button class="new-chat-btn" onclick="document.getElementById('newChatModal').classList.add('show')">
            <i class="fas fa-plus mr-2"></i>Start New Conversation
        </button>
        <div class="conv-list" id="convList">
            <div class="chat-empty" style="padding: 40px 20px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                <p style="margin-top: 10px;">Loading conversations...</p>
            </div>
        </div>
    </div>

    <!-- Chat Main -->
    <div class="chat-main" id="chatMain">
        <div class="chat-empty" id="chatEmpty">
            <i class="fas fa-comments"></i>
            <h3>Select a Conversation</h3>
            <p>Choose a company from the sidebar or start a new chat.</p>
        </div>
        <div id="chatActive" style="display:none; flex:1; display:none; flex-direction:column;">
            <div class="chat-top" id="chatTop">
                <span class="back-btn" onclick="goBack()"><i class="fas fa-arrow-left"></i></span>
                <div class="conv-avatar" id="chatAvatar" style="width:38px; height:38px; font-size:0.9rem;"></div>
                <div>
                    <h5 id="chatName"></h5>
                    <small id="chatStatus">Online</small>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="typing-indicator" id="typingIndicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
            <div class="chat-compose">
                <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1" onkeydown="handleKey(event)"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Chat Modal -->
<div class="modal-overlay" id="newChatModal">
    <div class="modal-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="font-weight-bold m-0"><i class="fas fa-plus-circle mr-2" style="color:#667eea;"></i>New Chat</h4>
            <button class="btn btn-sm btn-light rounded-circle" onclick="this.closest('.modal-overlay').classList.remove('show')"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-muted mb-3" style="font-size:0.9rem;">Start a conversation with a company</p>
        <?php if (empty($companies)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-building fa-2x mb-2" style="opacity:0.3;"></i>
                <p>Apply to jobs first to start chatting with companies.</p>
                <a href="browse_jobs.php" class="btn btn-sm" style="background:#667eea; color:white; border-radius:10px;">Browse Jobs</a>
            </div>
        <?php else: ?>
            <?php foreach ($companies as $comp): ?>
                <div class="company-pick" onclick="startChat(<?php echo $comp['id']; ?>, '<?php echo htmlspecialchars(addslashes($comp['company_name'])); ?>', '<?php echo htmlspecialchars($comp['logo'] ?? ''); ?>')">
                    <div class="conv-avatar" style="width:40px; height:40px; font-size:0.9rem;">
                        <?php if (!empty($comp['logo']) && file_exists('uploads/company_logos/' . $comp['logo'])): ?>
                            <img src="uploads/company_logos/<?php echo htmlspecialchars($comp['logo']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($comp['company_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="cp-name"><?php echo htmlspecialchars($comp['company_name']); ?></div>
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
    lastMsgId = 0;

    document.getElementById('chatEmpty').style.display = 'none';
    var ca = document.getElementById('chatActive');
    ca.style.display = 'flex';

    document.getElementById('chatName').textContent = companyName;
    var av = document.getElementById('chatAvatar');
    if (logo && logo !== '') {
        av.innerHTML = '<img src="uploads/company_logos/' + logo + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">';
    } else {
        av.innerHTML = companyName.charAt(0).toUpperCase();
    }

    var msgs = document.getElementById('chatMessages');
    msgs.innerHTML = '<div class="typing-indicator" id="typingIndicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>';

    document.getElementById('chatInput').focus();
    loadMessages();

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);

    // Highlight in sidebar
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    var sideItem = document.querySelector('.conv-item[data-id="' + companyId + '"]');
    if (sideItem) sideItem.classList.add('active');

    // Mobile
    document.getElementById('chatMain').classList.add('active-mobile');
}

function goBack() {
    document.getElementById('chatMain').classList.remove('active-mobile');
}

function loadMessages() {
    if (!activeCompany) return;
    fetch('api/chat_poll.php?with_type=company&with_id=' + activeCompany.id + '&since=' + lastMsgId)
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        var msgs = document.getElementById('chatMessages');
        data.messages.forEach(msg => {
            if (document.getElementById('msg-' + msg.id)) return;
            var div = document.createElement('div');
            div.className = 'chat-msg ' + (msg.sender_type === 'user' ? 'sent' : 'received');
            div.id = 'msg-' + msg.id;
            var time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            div.innerHTML = '<div class="chat-bubble">' + escapeHtml(msg.message) + '</div>' +
                '<div class="chat-time">' + time + (msg.sender_type === 'user' ? (msg.is_read ? ' <i class="fas fa-check-double"></i>' : ' <i class="fas fa-check"></i>') : '') + '</div>';
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

    fetch('api/chat_send.php', { method: 'POST', body: fd })
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
    fetch('api/chat_conversations.php')
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        var list = document.getElementById('convList');
        if (data.conversations.length === 0) {
            list.innerHTML = '<div class="chat-empty" style="padding:40px 20px;"><i class="fas fa-comments" style="font-size:2rem; opacity:0.3;"></i><p style="margin-top:10px; color:#94a3b8;">No conversations yet</p></div>';
            return;
        }
        var html = '';
        data.conversations.forEach(c => {
            var initial = c.company_name.charAt(0).toUpperCase();
            var active = (activeCompany && activeCompany.id == c.company_id) ? 'active' : '';
            html += '<div class="conv-item ' + active + '" data-id="' + c.company_id + '" data-name="' + c.company_name.toLowerCase() + '" onclick="startChat(' + c.company_id + ', \'' + c.company_name.replace(/'/g, "\\'") + '\', \'' + (c.logo || '') + '\')">';
            html += '<div class="conv-avatar">';
            if (c.logo && c.logo !== '') {
                html += '<img src="uploads/company_logos/' + c.logo + '" alt="">';
            } else {
                html += initial;
            }
            html += '</div>';
            html += '<div class="conv-info"><div class="conv-name">' + escapeHtml(c.company_name) + '</div><div class="conv-time">' + timeAgo(c.last_time) + '</div></div>';
            if (c.unread > 0) html += '<div class="conv-unread">' + c.unread + '</div>';
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
    document.querySelectorAll('.conv-item').forEach(el => {
        var name = el.getAttribute('data-name') || '';
        el.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

// Auto-resize textarea
var chatInput = document.getElementById('chatInput');
chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Initial load
loadConversations();
</script>
</body>
</html>
