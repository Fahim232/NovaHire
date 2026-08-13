/* NovaHire AI - Floating chat widget */
function aiChatInit() {
    const body = document.getElementById('aiChatBody');
    if (!body) return;
    body.innerHTML = '';
    aiChatAddMsg('bot',
        'Hi! I am your AI career assistant. Ask me about jobs, assessments, grooming, interviews, or your resume.' +
        '<div class="ai-quick"><button onclick="aiChatSet(\'Find jobs for me\')">Find jobs</button>' +
        '<button onclick="aiChatSet(\'Improve my resume\')">Improve resume</button>' +
        '<button onclick="aiChatSet(\'Start mock interview\')">Mock interview</button></div>'
    );
}

function aiChatToggle() {
    const panel = document.getElementById('aiChatPanel');
    const toggle = document.getElementById('aiChatToggle');
    const open = panel.classList.toggle('open');
    const icon = toggle.querySelector('i');
    const ping = toggle.querySelector('.ai-chat-ping');
    if (open) {
        icon.className = 'fas fa-times';
        if (ping) ping.style.display = 'none';
        if (!document.getElementById('aiChatBody').hasChildNodes()) aiChatInit();
        setTimeout(() => document.getElementById('aiChatInput').focus(), 200);
    } else {
        icon.className = 'fas fa-robot';
    }
}

function aiChatClose() {
    const panel = document.getElementById('aiChatPanel');
    const toggle = document.getElementById('aiChatToggle');
    panel.classList.remove('open');
    toggle.querySelector('i').className = 'fas fa-robot';
}

function aiChatSet(text) {
    const input = document.getElementById('aiChatInput');
    if (input) input.value = text;
    aiChatSend();
}

function aiChatAddMsg(role, html) {
    const body = document.getElementById('aiChatBody');
    if (!body) return;
    const div = document.createElement('div');
    div.className = 'ai-msg ' + (role === 'user' ? 'user' : 'bot');
    div.innerHTML = html;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
}

function aiChatTyping(on) {
    const body = document.getElementById('aiChatBody');
    const existing = document.getElementById('aiTyping');
    if (existing) existing.remove();
    if (on) {
        const div = document.createElement('div');
        div.className = 'ai-msg bot ai-typing';
        div.id = 'aiTyping';
        div.innerHTML = '<span></span><span></span><span></span>';
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }
}

function aiChatSend() {
    const input = document.getElementById('aiChatInput');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    aiChatAddMsg('user', msg.replace(/</g, '&lt;'));
    aiChatTyping(true);
    fetch((window.APP_URL || '') + '/api/ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message=' + encodeURIComponent(msg)
    })
    .then(r => r.json())
    .then(data => {
        aiChatTyping(false);
        let html = (data.reply || '').replace(/\n/g, '<br>');
        if (data.buttons && data.buttons.length) {
            html += '<div class="ai-quick">' + data.buttons.map(b =>
                '<button onclick="aiChatSet(\'' + b.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + '\')">' + b + '</button>'
            ).join('') + '</div>';
        }
        aiChatAddMsg('bot', html);
    })
    .catch(() => {
        aiChatTyping(false);
        aiChatAddMsg('bot', 'Sorry, something went wrong. Please try again.');
    });
}
