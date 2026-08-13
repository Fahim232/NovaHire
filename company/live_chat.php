<?php
session_start();
if (!isset($_SESSION['company_id'])) {
    header('location: ../login.php');
    exit();
}
include '../admin/dbcon.php';
include '../includes/functions.php';

$company_id = $_SESSION['company_id'];

$comp_q = mysqli_query($con, "SELECT * FROM companies WHERE id = $company_id");
$company = mysqli_fetch_assoc($comp_q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Live Chat - <?php echo htmlspecialchars($company['company_name']); ?> | NovaHire</title>
    <?php include '../links.php'; ?>
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; overflow: hidden; }
        .chat-wrap { display: flex; height: calc(100vh - 100px); margin: 0 auto; max-width: 1200px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; margin-top: 10px; }
        .chat-sidebar { width: 320px; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #059669, #10b981); }
        .sidebar-header h3 { color: white; font-weight: 800; font-size: 1.2rem; margin: 0; }
        .sidebar-header p { color: rgba(255,255,255,0.8); font-size: 0.8rem; margin: 4px 0 0; }
        .sidebar-search { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
        .sidebar-search input { width: 100%; border: 2px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; font-size: 0.9rem; outline: none; }
        .sidebar-search input:focus { border-color: #059669; }
        .conv-list { flex: 1; overflow-y: auto; }
        .conv-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f3f4f6; }
        .conv-item:hover { background: #f8fafc; }
        .conv-item.active { background: #ecfdf5; border-left: 3px solid #059669; }
        .conv-avatar { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; background: linear-gradient(135deg, #059669, #10b981); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1rem; }
        .conv-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
        .conv-info { flex: 1; min-width: 0; }
        .conv-name { font-weight: 700; font-size: 0.9rem; color: #0f172a; }
        .conv-time { font-size: 0.7rem; color: #94a3b8; }
        .conv-unread { background: #059669; color: white; font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .chat-main { flex: 1; display: flex; flex-direction: column; }
        .chat-top { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px; background: white; }
        .chat-top h5 { font-weight: 700; margin: 0; font-size: 1rem; }
        .chat-top small { color: #10b981; font-weight: 600; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 12px; background: #f8fafc; }
        .chat-msg { max-width: 70%; display: flex; flex-direction: column; }
        .chat-msg.sent { align-self: flex-end; }
        .chat-msg.received { align-self: flex-start; }
        .chat-bubble { padding: 12px 18px; border-radius: 18px; font-size: 0.9rem; line-height: 1.5; word-break: break-word; }
        .chat-msg.sent .chat-bubble { background: linear-gradient(135deg, #059669, #10b981); color: white; border-bottom-right-radius: 6px; }
        .chat-msg.received .chat-bubble { background: white; color: #0f172a; border-bottom-left-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .chat-time { font-size: 0.65rem; color: #94a3b8; margin-top: 4px; }
        .chat-msg.sent .chat-time { text-align: right; }
        .chat-compose { padding: 16px 24px; border-top: 1px solid #e5e7eb; background: white; display: flex; gap: 12px; align-items: flex-end; }
        .chat-input { flex: 1; border: 2px solid #e5e7eb; border-radius: 14px; padding: 12px 16px; font-size: 0.9rem; resize: none; outline: none; min-height: 46px; max-height: 120px; font-family: inherit; }
        .chat-input:focus { border-color: #059669; }
        .send-btn { background: linear-gradient(135deg, #059669, #10b981); color: white; border: none; width: 46px; height: 46px; border-radius: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .send-btn:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(5,150,105,0.4); }
        .send-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }
        .chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; background: #f8fafc; }
        .chat-empty i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        .chat-empty h3 { font-weight: 700; color: #64748b; }
        @media (max-width: 768px) { .chat-sidebar { width: 100%; } .chat-main { display: none; } .chat-main.active-mobile { display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: white; } .back-btn { display: block !important; } }
        .back-btn { display: none; color: #059669; cursor: pointer; font-size: 1.2rem; margin-right: 8px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg glass-nav fixed-top">
  <div class="container-fluid px-5 custom-nav-container">
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <div class="brand-icon mr-2"><i class="fas fa-layer-group"></i></div>
        <span class="brand-text">Nova<span class="brand-highlight">Hire</span></span>
      </a>
      <button class="navbar-toggler custom-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
        <span class="fas fa-bars fa-lg text-dark"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-between" id="collapsibleNavbar">
        <ul class="navbar-nav mx-auto center-menu">
           <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
           <li class="nav-item"><a class="nav-link" href="post_job.php">Post Job</a></li>
           <li class="nav-item"><a class="nav-link" href="view_applicants.php">Applicants</a></li>
           <li class="nav-item"><a class="nav-link" href="live_chat.php" style="font-weight:700; color:#059669;">Live Chat</a></li>
        </ul>
        <ul class="navbar-nav align-items-center right-menu">
            <li class="nav-item dropdown">
                <a class="nav-link user-pill dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                   <div class="user-avatar-sm"><i class="fas fa-building"></i></div>
                   <span class="user-name-text"><?php echo htmlspecialchars($company['company_name']); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 user-menu-dropdown">
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-building mr-2 text-muted"></i> Company Profile</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </li>
        </ul>
      </div>
  </div>
</nav>

<div class="container" style="height: calc(100vh - 100px); padding-top: 10px;">
<div class="chat-wrap">
    <div class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-comments mr-2"></i>Live Chat</h3>
            <p>Chat with job applicants</p>
        </div>
        <div class="sidebar-search">
            <input type="text" placeholder="Search users..." id="convSearch" oninput="filterConv(this.value)">
        </div>
        <div class="conv-list" id="convList">
            <div class="chat-empty" style="padding:40px 20px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><p style="margin-top:10px;">Loading...</p></div>
        </div>
    </div>
    <div class="chat-main" id="chatMain">
        <div class="chat-empty" id="chatEmpty">
            <i class="fas fa-comments"></i>
            <h3>Select a Conversation</h3>
            <p>Choose a user from the sidebar to start chatting.</p>
        </div>
        <div id="chatActive" style="display:none; flex-direction:column; flex:1;">
            <div class="chat-top" id="chatTop">
                <span class="back-btn" onclick="goBack()"><i class="fas fa-arrow-left"></i></span>
                <div class="conv-avatar" id="chatAvatar" style="width:38px; height:38px; font-size:0.9rem;"></div>
                <div><h5 id="chatName"></h5><small id="chatStatus">Online</small></div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-compose">
                <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1" onkeydown="handleKey(event)"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>
</div>

<script>
let activeUser = null;
let lastMsgId = 0;
let pollTimer = null;

function startChat(userId, userName, profile) {
    activeUser = { id: userId, name: userName, profile: profile };
    lastMsgId = 0;
    document.getElementById('chatEmpty').style.display = 'none';
    var ca = document.getElementById('chatActive');
    ca.style.display = 'flex';
    document.getElementById('chatName').textContent = userName;
    var av = document.getElementById('chatAvatar');
    if (profile && profile !== '') {
        av.innerHTML = '<img src="../' + profile + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">';
    } else {
        av.innerHTML = userName.charAt(0).toUpperCase();
    }
    document.getElementById('chatMessages').innerHTML = '';
    document.getElementById('chatInput').focus();
    loadMessages();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    var sideItem = document.querySelector('.conv-item[data-id="' + userId + '"]');
    if (sideItem) sideItem.classList.add('active');
    document.getElementById('chatMain').classList.add('active-mobile');
}

function goBack() { document.getElementById('chatMain').classList.remove('active-mobile'); }

function loadMessages() {
    if (!activeUser) return;
    fetch('api_chat_poll.php?with_type=user&with_id=' + activeUser.id + '&since=' + lastMsgId)
    .then(r => r.json()).then(data => {
        if (!data.success) return;
        var msgs = document.getElementById('chatMessages');
        data.messages.forEach(msg => {
            if (document.getElementById('msg-' + msg.id)) return;
            var div = document.createElement('div');
            div.className = 'chat-msg ' + (msg.sender_type === 'company' ? 'sent' : 'received');
            div.id = 'msg-' + msg.id;
            var time = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            div.innerHTML = '<div class="chat-bubble">' + escapeHtml(msg.message) + '</div><div class="chat-time">' + time + (msg.sender_type === 'company' ? (msg.is_read ? ' <i class="fas fa-check-double"></i>' : ' <i class="fas fa-check"></i>') : '') + '</div>';
            msgs.appendChild(div);
            if (msg.id > lastMsgId) lastMsgId = msg.id;
        });
        msgs.scrollTop = msgs.scrollHeight;
    });
}

function sendMessage() {
    if (!activeUser) return;
    var input = document.getElementById('chatInput');
    var msg = input.value.trim();
    if (!msg) return;
    document.getElementById('sendBtn').disabled = true;
    input.value = '';
    input.style.height = 'auto';
    var fd = new FormData();
    fd.append('receiver_type', 'user');
    fd.append('receiver_id', activeUser.id);
    fd.append('message', msg);
    fetch('api_chat_send.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => {
        document.getElementById('sendBtn').disabled = false;
        if (data.success) { loadMessages(); loadConversations(); }
        else input.value = msg;
    }).catch(() => { document.getElementById('sendBtn').disabled = false; input.value = msg; });
}

function handleKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }
function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

function loadConversations() {
    fetch('api_chat_conversations.php').then(r => r.json()).then(data => {
        if (!data.success) return;
        var list = document.getElementById('convList');
        if (data.conversations.length === 0) {
            list.innerHTML = '<div class="chat-empty" style="padding:40px 20px;"><i class="fas fa-user-friends" style="font-size:2rem; opacity:0.3;"></i><p style="margin-top:10px; color:#94a3b8;">No conversations yet</p></div>';
            return;
        }
        var html = '';
        data.conversations.forEach(c => {
            var active = (activeUser && activeUser.id == c.user_id) ? 'active' : '';
            html += '<div class="conv-item ' + active + '" data-id="' + c.user_id + '" data-name="' + c.username.toLowerCase() + '" onclick="startChat(' + c.user_id + ', \'' + c.username.replace(/'/g, "\\'") + '\', \'' + (c.profile || '') + '\')">';
            html += '<div class="conv-avatar">';
            if (c.profile) html += '<img src="../' + c.profile + '" alt="">';
            else html += c.username.charAt(0).toUpperCase();
            html += '</div>';
            html += '<div class="conv-info"><div class="conv-name">' + escapeHtml(c.username) + '</div><div class="conv-time">' + timeAgo(c.last_time) + '</div></div>';
            if (c.unread > 0) html += '<div class="conv-unread">' + c.unread + '</div>';
            html += '</div>';
        });
        list.innerHTML = html;
    });
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
        el.style.display = (el.getAttribute('data-name') || '').includes(q) ? 'flex' : 'none';
    });
}

document.getElementById('chatInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

loadConversations();
</script>
</body>
</html>
