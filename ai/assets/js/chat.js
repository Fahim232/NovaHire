/* NovaHire AI - Floating Chat Widget v3 */
(function(){
    var panel, toggle, body, input, sendBtn;

    function aiChatInit() {
        panel  = document.getElementById('aiChatPanel');
        toggle = document.getElementById('aiChatToggle');
        body   = document.getElementById('aiChatBody');
        input  = document.getElementById('aiChatInput');
        if (input) sendBtn = input.nextElementSibling;
    }

    function aiChatToggle() {
        if (!panel) aiChatInit();
        var open = panel.classList.toggle('open');
        var icon = toggle.querySelector('i');
        var ping = toggle.querySelector('.ai-chat-ping');
        if (open) {
            icon.className = 'fas fa-times';
            if (ping) ping.style.display = 'none';
            setTimeout(function(){ if(input) input.focus(); }, 250);
        } else {
            icon.className = 'fas fa-robot';
        }
    }
    window.aiChatToggle = aiChatToggle;

    function aiChatClose() {
        if (!panel) aiChatInit();
        panel.classList.remove('open');
        toggle.querySelector('i').className = 'fas fa-robot';
    }
    window.aiChatClose = aiChatClose;

    function aiChatSet(text) {
        if (!input) aiChatInit();
        input.value = text;
        aiChatSend();
    }
    window.aiChatSet = aiChatSet;

    function aiChatTime() {
        var d = new Date();
        var h = d.getHours(), m = d.getMinutes();
        var ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
    }

    function aiChatAddMsg(role, html) {
        if (!body) aiChatInit();
        var wrap = document.createElement('div');
        wrap.className = 'ai-msg-wrap ai-msg-' + role;

        var div = document.createElement('div');
        div.className = 'ai-msg ' + (role === 'user' ? 'user' : 'bot');
        div.innerHTML = html;
        wrap.appendChild(div);

        var time = document.createElement('div');
        time.className = 'ai-msg-time';
        time.textContent = aiChatTime();
        wrap.appendChild(time);

        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
    }

    function aiChatTyping(on) {
        if (!body) aiChatInit();
        var existing = document.getElementById('aiTyping');
        if (existing) existing.remove();
        if (on) {
            var wrap = document.createElement('div');
            wrap.className = 'ai-msg-wrap ai-msg-bot';
            wrap.id = 'aiTyping';
            var div = document.createElement('div');
            div.className = 'ai-msg bot ai-typing';
            div.innerHTML = '<span></span><span></span><span></span>';
            wrap.appendChild(div);
            body.appendChild(wrap);
            body.scrollTop = body.scrollHeight;
        }
    }

    function aiChatSend() {
        if (!input) aiChatInit();
        var msg = input.value.trim();
        if (!msg) return;
        input.value = '';
        if (sendBtn) sendBtn.disabled = true;

        aiChatAddMsg('user', msg.replace(/</g, '&lt;'));

        setTimeout(function(){ aiChatTyping(true); }, 150);

        var url = (window.APP_URL || '') + '/api/ai_chat.php';
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(msg)
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            var delay = Math.min(1400, Math.max(400, (data.reply || '').length * 2));
            setTimeout(function(){
                aiChatTyping(false);
                var html = (data.reply || '').replace(/\n/g, '<br>');
                if (data.buttons && data.buttons.length) {
                    html += '<div class="ai-quick">' + data.buttons.map(function(b){
                        return '<button onclick="aiChatSet(\'' + b.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + '\')">' + b + '</button>';
                    }).join('') + '</div>';
                }
                aiChatAddMsg('bot', html);
                if (sendBtn) sendBtn.disabled = false;
                input.focus();
            }, delay);
        })
        .catch(function(){
            aiChatTyping(false);
            aiChatAddMsg('bot', '<span class="ai-error">Something went wrong. Please try again.</span>');
            if (sendBtn) sendBtn.disabled = false;
        });
    }
    window.aiChatSend = aiChatSend;

    /* Auto-init on DOM ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', aiChatInit);
    } else {
        aiChatInit();
    }
})();
