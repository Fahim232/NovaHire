<?php
session_start();
if (!isset($_SESSION['company_id'])) {
    header('location: ../auth/login.php');
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat - <?php echo htmlspecialchars($company['company_name']); ?> | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <style>
        :root {
            --lc-bg: #f4f6fb;
            --lc-card: #ffffff;
            --lc-border: #e5e9f2;
            --lc-text: #1e293b;
            --lc-muted: #64748b;
            --lc-primary: #4f46e5;
            --lc-primary-2: #7c3aed;
            --lc-soft: #eef2ff;
            --lc-input: #f8fafc;
            --lc-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
            --lc-bubble-in: #ffffff;
        }
        [data-theme="dark"] {
            --lc-bg: #0f172a;
            --lc-card: #111827;
            --lc-border: #28334a;
            --lc-text: #e8edff;
            --lc-muted: #94a3b8;
            --lc-primary: #8b5cf6;
            --lc-primary-2: #a78bfa;
            --lc-soft: #1e293b;
            --lc-input: #0d1526;
            --lc-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
            --lc-bubble-in: #1a2234;
        }

        * { box-sizing: border-box; }
        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.10), transparent 28%),
                radial-gradient(circle at 92% 8%, rgba(217, 70, 239, 0.08), transparent 26%),
                var(--lc-bg);
            color: var(--lc-text);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .lc-wrap { max-width: 1240px; margin: 0 auto; padding: 26px 24px 40px; }

        /* ── Hero ── */
        .lc-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #a855f7 100%);
            border-radius: 22px;
            padding: 26px 34px;
            color: #fff;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.28);
            display: flex; align-items: center; justify-content: space-between;
            gap: 18px; flex-wrap: wrap;
            margin-bottom: 22px;
        }
        .lc-hero::before {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
        }
        .lc-hero::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -110px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .lc-hero h1 { font-weight: 800; font-size: 1.6rem; color: #fff; margin: 0 0 6px; }
        .lc-hero p { color: rgba(255, 255, 255, 0.85); margin: 0; font-size: 0.92rem; }
        .lc-hero-online {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 10px 18px;
            border-radius: 14px;
            font-size: 0.85rem; font-weight: 700;
        }
        .lc-hero-online .dot { width: 9px; height: 9px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.28); animation: lcPulse 1.8s infinite; }
        @keyframes lcPulse { 0%,100% { box-shadow: 0 0 0 3px rgba(52,211,153,.25);} 50% { box-shadow: 0 0 0 7px rgba(52,211,153,.08);} }

        /* ── Chat frame ── */
        .lc-frame {
            display: flex;
            height: calc(100vh - 260px);
            min-height: 520px;
            background: var(--lc-card);
            border: 1px solid var(--lc-border);
            border-radius: 20px;
            box-shadow: var(--lc-shadow);
            overflow: hidden;
        }

        /* ── Sidebar ── */
        .lc-sidebar { width: 330px; border-right: 1px solid var(--lc-border); display: flex; flex-direction: column; flex-shrink: 0; background: var(--lc-card); }
        .lc-sb-head { padding: 18px 18px 12px; }
        .lc-sb-search { position: relative; }
        .lc-sb-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--lc-muted); font-size: 0.85rem; }
        .lc-sb-search input {
            width: 100%;
            background: var(--lc-input);
            border: 1.5px solid var(--lc-border);
            color: var(--lc-text);
            border-radius: 12px;
            padding: 11px 14px 11px 38px;
            font-size: 0.88rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .lc-sb-search input:focus { border-color: var(--lc-primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); }
        .lc-sb-search input::placeholder { color: var(--lc-muted); }

        .lc-conv-list { flex: 1; overflow-y: auto; padding: 6px 10px 14px; }
        .lc-conv-list::-webkit-scrollbar, .lc-msgs::-webkit-scrollbar { width: 6px; }
        .lc-conv-list::-webkit-scrollbar-thumb, .lc-msgs::-webkit-scrollbar-thumb { background: var(--lc-border); border-radius: 10px; }

        .lc-conv {
            display: flex; align-items: center; gap: 13px;
            padding: 12px 12px;
            border-radius: 14px;
            cursor: pointer;
            transition: background .18s ease, transform .18s ease;
            border: 1px solid transparent;
            margin-bottom: 4px;
            animation: lcRise .35s ease both;
        }
        @keyframes lcRise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .lc-conv:hover { background: var(--lc-soft); transform: translateY(-1px); }
        .lc-conv.active { background: rgba(79, 70, 229, 0.10); border-color: rgba(79, 70, 229, 0.30); }
        .lc-conv.hidden { display: none; }

        .lc-cavatar {
            width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 1.05rem;
            box-shadow: 0 5px 14px rgba(0, 0, 0, 0.16);
            position: relative;
            overflow: hidden;
        }
        .lc-cavatar img { width: 100%; height: 100%; object-fit: cover; }
        .lc-cavatar .lc-on {
            position: absolute; bottom: 1px; right: 1px;
            width: 12px; height: 12px; border-radius: 50%;
            background: #10b981; border: 2.5px solid #fff;
        }

        .lc-cinfo { flex: 1; min-width: 0; }
        .lc-crow { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .lc-cname { font-weight: 700; font-size: 0.9rem; color: var(--lc-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lc-ctime { font-size: 0.68rem; color: var(--lc-muted); flex-shrink: 0; }
        .lc-cprev { font-size: 0.78rem; color: var(--lc-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; }
        .lc-cprev b { font-weight: 600; color: var(--lc-text); }
        .lc-cunread {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-primary-2));
            color: #fff;
            font-size: 0.68rem; font-weight: 800;
            min-width: 20px; height: 20px;
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 6px;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.4);
        }

        /* ── Main ── */
        .lc-main { flex: 1; display: flex; flex-direction: column; min-width: 0; background: var(--lc-bg); }

        .lc-empty {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: var(--lc-muted);
            padding: 40px; text-align: center;
        }
        .lc-empty-ico {
            width: 96px; height: 96px; border-radius: 50%;
            background: var(--lc-soft);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: var(--lc-primary);
            margin-bottom: 22px;
            animation: lcFloat 3s ease-in-out infinite;
        }
        @keyframes lcFloat { 0%,100% { transform: translateY(0);} 50% { transform: translateY(-8px);} }
        .lc-empty h3 { font-weight: 800; color: var(--lc-text); margin-bottom: 6px; }
        .lc-empty p { font-size: 0.9rem; margin: 0; }

        .lc-chat { display: none; flex-direction: column; flex: 1; min-height: 0; }
        .lc-chat.show { display: flex; }

        .lc-top {
            display: flex; align-items: center; gap: 13px;
            padding: 14px 22px;
            border-bottom: 1px solid var(--lc-border);
            background: var(--lc-card);
        }
        .lc-top-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 1rem;
            flex-shrink: 0; overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
            position: relative;
        }
        .lc-top-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .lc-top-info { flex: 1; min-width: 0; }
        .lc-top-name { font-weight: 800; font-size: 0.98rem; color: var(--lc-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lc-top-status { font-size: 0.76rem; color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .lc-top-status .dot { width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block; }
        .lc-back {
            display: none;
            background: var(--lc-soft);
            border: 1px solid var(--lc-border);
            color: var(--lc-text);
            width: 38px; height: 38px; border-radius: 11px;
            cursor: pointer; font-size: 1rem;
            align-items: center; justify-content: center;
            transition: all .18s ease;
        }
        .lc-back:hover { background: var(--lc-primary); color: #fff; border-color: transparent; }

        .lc-msgs {
            flex: 1; overflow-y: auto;
            padding: 22px;
            display: flex; flex-direction: column;
            gap: 4px;
        }
        .lc-day {
            align-self: center;
            font-size: 0.7rem; font-weight: 700;
            color: var(--lc-muted);
            background: var(--lc-soft);
            border: 1px solid var(--lc-border);
            padding: 5px 14px;
            border-radius: 20px;
            margin: 10px 0 14px;
        }
        .lc-msg { max-width: 68%; display: flex; flex-direction: column; animation: lcPop .22s ease both; }
        @keyframes lcPop { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .lc-msg.sent { align-self: flex-end; align-items: flex-end; }
        .lc-msg.recv { align-self: flex-start; align-items: flex-start; }
        .lc-bubble {
            padding: 11px 16px;
            border-radius: 18px;
            font-size: 0.9rem; line-height: 1.55;
            word-break: break-word;
            white-space: pre-wrap;
        }
        .lc-msg.sent .lc-bubble {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-primary-2));
            color: #fff;
            border-bottom-right-radius: 6px;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.28);
        }
        .lc-msg.recv .lc-bubble {
            background: var(--lc-bubble-in);
            color: var(--lc-text);
            border-bottom-left-radius: 6px;
            border: 1px solid var(--lc-border);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }
        .lc-msg .lc-time {
            font-size: 0.66rem; color: var(--lc-muted);
            margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;
        }
        .lc-msg .lc-time .tick { color: var(--lc-primary); font-size: 0.7rem; }
        .lc-msg.sent .lc-time .tick.read { color: #22d3ee; }

        .lc-typing { display: inline-flex; gap: 4px; align-items: center; padding: 12px 16px; }
        .lc-typing span { width: 7px; height: 7px; border-radius: 50%; background: var(--lc-muted); animation: lcBounce 1.2s infinite; }
        .lc-typing span:nth-child(2) { animation-delay: .15s; }
        .lc-typing span:nth-child(3) { animation-delay: .3s; }
        @keyframes lcBounce { 0%,60%,100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-5px); opacity: 1; } }

        /* ── Compose ── */
        .lc-compose {
            padding: 14px 20px 16px;
            border-top: 1px solid var(--lc-border);
            background: var(--lc-card);
            display: flex; gap: 12px; align-items: flex-end;
        }
        .lc-emoji-btn {
            background: var(--lc-soft);
            border: 1.5px solid var(--lc-border);
            color: var(--lc-muted);
            width: 46px; height: 46px; border-radius: 13px;
            cursor: pointer; font-size: 1.15rem;
            flex-shrink: 0;
            transition: all .18s ease;
        }
        .lc-emoji-btn:hover { background: var(--lc-primary); color: #fff; border-color: transparent; }
        .lc-input-wrap { flex: 1; position: relative; }
        .lc-input {
            width: 100%;
            background: var(--lc-input);
            border: 1.5px solid var(--lc-border);
            color: var(--lc-text);
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 0.9rem;
            resize: none;
            outline: none;
            min-height: 48px;
            max-height: 130px;
            font-family: inherit;
            line-height: 1.5;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .lc-input:focus { border-color: var(--lc-primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); }
        .lc-input::placeholder { color: var(--lc-muted); }
        .lc-emoji-panel {
            display: none;
            position: absolute; bottom: 100%; left: 0; right: 0;
            background: var(--lc-card);
            border: 1px solid var(--lc-border);
            border-radius: 14px;
            padding: 10px;
            box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.12);
            margin-bottom: 8px;
            z-index: 5;
        }
        .lc-emoji-panel.show { display: block; animation: lcPop .2s ease; }
        .lc-emoji-panel span { font-size: 1.25rem; cursor: pointer; padding: 4px; border-radius: 8px; transition: background .15s ease; display: inline-block; }
        .lc-emoji-panel span:hover { background: var(--lc-soft); }
        .lc-send {
            background: linear-gradient(135deg, var(--lc-primary), var(--lc-primary-2));
            color: #fff; border: none;
            width: 48px; height: 48px; border-radius: 14px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
            transition: all .18s ease;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
        }
        .lc-send:hover { transform: scale(1.06); }
        .lc-send:active { transform: scale(0.96); }
        .lc-send:disabled { background: var(--lc-border); cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── Sidebar empty ── */
        .lc-sb-empty { text-align: center; padding: 50px 20px; color: var(--lc-muted); }
        .lc-sb-empty i { font-size: 2.4rem; opacity: .4; margin-bottom: 14px; display: block; }

        @media (max-width: 900px) {
            .lc-frame { height: calc(100vh - 230px); }
            .lc-sidebar { width: 100%; }
            .lc-main { display: none; }
            .lc-main.active-mobile { display: flex; position: fixed; inset: 0; z-index: 9999; background: var(--lc-card); }
            .lc-back { display: flex; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/company_header.php'; ?>

<div class="lc-wrap">
    <div class="lc-hero">
        <div>
            <h1><i class="fas fa-comments mr-2"></i>Live Chat</h1>
            <p>Chat instantly with candidates who reach out to your company.</p>
        </div>
        <span class="lc-hero-online"><span class="dot"></span>Replies usually within minutes</span>
    </div>

    <div class="lc-frame">
        <div class="lc-sidebar" id="lcSidebar">
            <div class="lc-sb-head">
                <div class="lc-sb-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="convSearch" placeholder="Search conversations..." oninput="filterConv(this.value)">
                </div>
            </div>
            <div class="lc-conv-list" id="convList">
                <div class="lc-sb-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading conversations...</p></div>
            </div>
        </div>

        <div class="lc-main" id="lcMain">
            <div class="lc-empty" id="chatEmpty">
                <div class="lc-empty-ico"><i class="fas fa-comments"></i></div>
                <h3>Select a Conversation</h3>
                <p>Choose a candidate from the list to start chatting.</p>
            </div>

            <div class="lc-chat" id="chatActive">
                <div class="lc-top">
                    <button class="lc-back" onclick="goBack()"><i class="fas fa-arrow-left"></i></button>
                    <div class="lc-top-avatar" id="chatAvatar"></div>
                    <div class="lc-top-info">
                        <div class="lc-top-name" id="chatName"></div>
                        <span class="lc-top-status"><span class="dot"></span>Active now</span>
                    </div>
                </div>
                <div class="lc-msgs" id="chatMessages"></div>
                <div class="lc-compose">
                    <button class="lc-emoji-btn" id="emojiBtn" onclick="toggleEmoji(event)"><i class="far fa-face-smile"></i></button>
                    <div class="lc-input-wrap">
                        <div class="lc-emoji-panel" id="emojiPanel"></div>
                        <textarea class="lc-input" id="chatInput" rows="1" placeholder="Type your message..." onkeydown="handleKey(event)"></textarea>
                    </div>
                    <button class="lc-send" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let activeUser = null;
let lastMsgId = 0;
let pollTimer = null;
let convTimer = null;
let lastRenderDate = '';

const GRADIENTS = [
    ['#6366f1', '#8b5cf6'],
    ['#0ea5e9', '#06b6d4'],
    ['#10b981', '#34d399'],
    ['#f59e0b', '#f97316'],
    ['#ec4899', '#f43f5e'],
    ['#14b8a6', '#0d9488'],
];
const EMOJIS = ['👍','👋','😊','🙏','🎉','📅','✅','📞','📩','🤝','💼','⭐','🔥','✨','🚀','💡','🙌','❤️'];

function avatarGradient(name) {
    const g = GRADIENTS[Math.abs(hash(name)) % GRADIENTS.length];
    return 'linear-gradient(135deg, ' + g[0] + ', ' + g[1] + ')';
}
function hash(s) { let h = 0; for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0; return Math.abs(h); }
function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

function buildEmojiPanel() {
    document.getElementById('emojiPanel').innerHTML = EMOJIS.map(e => '<span onclick="insertEmoji(\'' + e + '\', event)">' + e + '</span>').join('');
}
function toggleEmoji(e) {
    e.stopPropagation();
    document.getElementById('emojiPanel').classList.toggle('show');
}
function insertEmoji(em, e) {
    e.stopPropagation();
    const inp = document.getElementById('chatInput');
    inp.value += em;
    inp.focus();
}
document.addEventListener('click', function() {
    document.getElementById('emojiPanel').classList.remove('show');
});

function startChat(userId, userName, profile) {
    activeUser = { id: userId, name: userName, profile: profile };
    window.LC_ACTIVE_ID = parseInt(userId, 10);
    lastMsgId = 0;
    lastRenderDate = '';
    document.getElementById('chatEmpty').style.display = 'none';
    const ca = document.getElementById('chatActive');
    ca.classList.add('show');
    document.getElementById('chatName').textContent = userName;

    const av = document.getElementById('chatAvatar');
    av.innerHTML = '';
    if (profile) {
        const img = document.createElement('img');
        img.src = '../seeker/images/' + profile;
        img.alt = '';
        img.onerror = function() { this.outerHTML = nameInitial(userName); };
        av.appendChild(img);
    } else {
        av.style.background = avatarGradient(userName);
        av.innerHTML = nameInitial(userName);
    }

    document.getElementById('chatMessages').innerHTML = '';
    document.getElementById('chatInput').focus();
    loadMessages();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);
    if (convTimer) clearInterval(convTimer);
    convTimer = setInterval(loadConversations, 15000);
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    const sideItem = document.querySelector('.conv-item[data-id="' + userId + '"]');
    if (sideItem) sideItem.classList.add('active');
    document.getElementById('lcMain').classList.add('active-mobile');
}

function nameInitial(name) {
    return '<span style="background:' + avatarGradient(name) + ';width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;">' + esc(name.charAt(0).toUpperCase()) + '</span>';
}

function goBack() { document.getElementById('lcMain').classList.remove('active-mobile'); }

function dayLabel(iso) {
    const d = new Date(iso);
    const today = new Date();
    const startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const startD = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.round((startToday - startD) / 86400000);
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

function loadMessages() {
    if (!activeUser) return;
    fetch('api_chat_poll.php?with_type=user&with_id=' + activeUser.id + '&since=' + lastMsgId)
    .then(r => r.json()).then(data => {
        if (!data.success) return;
        const msgs = document.getElementById('chatMessages');
        data.messages.forEach(msg => {
            if (document.getElementById('msg-' + msg.id)) return;
            const sent = msg.sender_type === 'company';
            const dLabel = dayLabel(msg.created_at);
            if (dLabel !== lastRenderDate) {
                const day = document.createElement('div');
                day.className = 'lc-day';
                day.textContent = dLabel;
                msgs.appendChild(day);
                lastRenderDate = dLabel;
            }
            const div = document.createElement('div');
            div.className = 'lc-msg ' + (sent ? 'sent' : 'recv');
            div.id = 'msg-' + msg.id;
            const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            let ticks = '';
            if (sent) ticks = '<span class="tick' + (msg.is_read ? ' read' : '') + '"><i class="fas fa-check-double"></i></span>';
            div.innerHTML = '<div class="lc-bubble">' + esc(msg.message) + '</div><span class="lc-time">' + time + ticks + '</span>';
            msgs.appendChild(div);
            if (msg.id > lastMsgId) lastMsgId = msg.id;
        });
        msgs.scrollTop = msgs.scrollHeight;
    }).catch(function() {});
}

function sendMessage() {
    if (!activeUser) return;
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    document.getElementById('sendBtn').disabled = true;
    input.value = '';
    input.style.height = 'auto';
    const fd = new FormData();
    fd.append('receiver_type', 'user');
    fd.append('receiver_id', activeUser.id);
    fd.append('message', msg);
    fetch('api_chat_send.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => {
        document.getElementById('sendBtn').disabled = false;
        if (data.success) { loadMessages(); loadConversations(true); }
        else input.value = msg;
    }).catch(function() { document.getElementById('sendBtn').disabled = false; input.value = msg; });
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function loadConversations(keepScroll) {
    const list = document.getElementById('convList');
    fetch('api_chat_conversations.php').then(r => r.json()).then(data => {
        if (!data.success) return;
        const scroll = list.scrollTop;
        if (data.conversations.length === 0) {
            list.innerHTML = '<div class="lc-sb-empty"><i class="fas fa-user-friends"></i><p>No conversations yet</p></div>';
            return;
        }
        const q = (document.getElementById('convSearch').value || '').toLowerCase();
        let html = '';
        data.conversations.forEach(c => {
            const name = esc(c.username);
            const active = (activeUser && activeUser.id == c.user_id) ? 'active' : '';
            const prev = c.last_sender === 'company' ? '<b>You:</b> ' : '';
            const preview = esc(c.last_message ? c.last_message.slice(0, 60) : 'New conversation');
            const unread = c.unread > 0 ? '<span class="lc-cunread">' + c.unread + '</span>' : '';
            const profileImg = c.profile
                ? '<img src="../seeker/images/' + esc(c.profile) + '" alt="" onerror="this.parentNode.removeChild(this)">'
                : '';
            html += '<div class="lc-conv ' + active + ' conv-item" data-id="' + c.user_id + '" data-name="' + name.toLowerCase() + '" onclick="startChat(' + c.user_id + ', \'' + name.replace(/'/g, "\\'") + '\', \'' + (c.profile || '').replace(/'/g, "\\'") + '\')" style="animation-delay:' + (c.user_id % 5) * 40 + 'ms">';
            html += '<div class="lc-cavatar" style="background:' + avatarGradient(c.username) + '">' + profileImg + '<span class="lc-on"></span></div>';
            html += '<div class="lc-cinfo">';
            html += '<div class="lc-crow"><span class="lc-cname">' + name + '</span><span class="lc-ctime">' + timeAgo(c.last_time) + '</span></div>';
            html += '<div class="lc-cprev">' + prev + preview + '</div>';
            html += '</div>' + unread;
            html += '</div>';
        });
        list.innerHTML = html;
        if (keepScroll) list.scrollTop = scroll;
        if (activeUser) {
            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            const sideItem = document.querySelector('.conv-item[data-id="' + activeUser.id + '"]');
            if (sideItem) sideItem.classList.add('active');
        }
        filterConv(q);
    }).catch(function() {});
}

function timeAgo(dt) {
    if (!dt) return '';
    const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return Math.floor(diff / 86400) + 'd';
}

function filterConv(q) {
    q = (q || '').toLowerCase();
    document.querySelectorAll('.conv-item').forEach(el => {
        el.classList.toggle('hidden', q && !(el.getAttribute('data-name') || '').includes(q));
    });
}

document.getElementById('chatInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 130) + 'px';
});

buildEmojiPanel();
loadConversations();
</script>
</body>
</html>
