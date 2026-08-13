<?php
session_start();
if (!isset($_SESSION['company_id'])) {
    header('Location: ../company_login.php');
    exit;
}

include '../admin/dbcon.php';
include '../includes/functions.php';

$company_id = (int)$_SESSION['company_id'];

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_type = mysqli_real_escape_string($con, $_POST['receiver_type']);
    $receiver_id = intval($_POST['receiver_id']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $message = mysqli_real_escape_string($con, $_POST['message']);
    $related_job_id = !empty($_POST['related_job_id']) ? intval($_POST['related_job_id']) : null;
    
    $msg_id = send_message($con, 'company', $company_id, $receiver_type, $receiver_id, $subject, $message, $related_job_id);
    
    if ($msg_id) {
        $success_msg = "Message sent successfully!";
    } else {
        $error_msg = "Failed to send message. Please try again.";
    }
}

// Determine which conversation to show
$active_conversation = null;
$with_param = isset($_GET['with']) ? $_GET['with'] : '';
if (!empty($with_param)) {
    $parts = explode('_', $with_param);
    if (count($parts) === 2) {
        $active_conversation = [
            'type' => $parts[0],
            'id' => intval($parts[1])
        ];
    }
}

// Mark conversation as read if viewing
if ($active_conversation) {
    mark_conversation_read($con, 'company', $company_id, $active_conversation['type'], $active_conversation['id']);
}

// Get all conversations (grouped by other party)
$conversations_q = mysqli_query($con, "
    SELECT m.*,
        CASE 
            WHEN m.sender_type = 'company' THEN (SELECT company_name FROM companies WHERE id = m.sender_id)
            WHEN m.sender_type = 'user' THEN (SELECT username FROM user_info WHERE id = m.sender_id)
            ELSE 'System'
        END as sender_name,
        CASE 
            WHEN m.receiver_type = 'company' THEN (SELECT company_name FROM companies WHERE id = m.receiver_id)
            WHEN m.receiver_type = 'user' THEN (SELECT username FROM user_info WHERE id = m.receiver_id)
            ELSE 'System'
        END as receiver_name,
        (SELECT COUNT(*) FROM messages WHERE sender_type = m.sender_type AND sender_id = m.sender_id AND receiver_type = m.receiver_type AND receiver_id = m.receiver_id AND is_read = 0 AND receiver_type = 'company' AND receiver_id = $company_id) as unread_count
    FROM messages m
    WHERE (m.sender_type = 'company' AND m.sender_id = $company_id AND m.is_deleted_by_sender = 0)
       OR (m.receiver_type = 'company' AND m.receiver_id = $company_id AND m.is_deleted_by_receiver = 0)
    GROUP BY 
        CASE 
            WHEN m.sender_type = 'company' THEN CONCAT(m.receiver_type, '_', m.receiver_id)
            ELSE CONCAT(m.sender_type, '_', m.sender_id)
        END
    ORDER BY m.created_at DESC
");

// Build conversation list
$conversation_list = [];
while ($row = mysqli_fetch_assoc($conversations_q)) {
    if ($row['sender_type'] === 'company' && $row['sender_id'] == $company_id) {
        $other_type = $row['receiver_type'];
        $other_id = $row['receiver_id'];
        $other_name = $row['receiver_name'];
    } else {
        $other_type = $row['sender_type'];
        $other_id = $row['sender_id'];
        $other_name = $row['sender_name'];
    }
    $key = $other_type . '_' . $other_id;
    if (!isset($conversation_list[$key])) {
        $conversation_list[$key] = [
            'type' => $other_type,
            'id' => $other_id,
            'name' => $other_name,
            'last_message' => $row['message'],
            'last_time' => $row['created_at'],
            'unread' => $row['unread_count'],
        ];
    }
}

// Get messages for active conversation
$messages = [];
$active_name = '';
if ($active_conversation) {
    $messages = get_conversation($con, 'company', $company_id, $active_conversation['type'], $active_conversation['id'], 50);
    
    // Get the name of the other party
    if ($active_conversation['type'] === 'user') {
        $other_q = mysqli_query($con, "SELECT username FROM user_info WHERE id = " . $active_conversation['id']);
        $other_info = mysqli_fetch_assoc($other_q);
        $active_name = $other_info['username'] ?? 'User';
    } else {
        $other_q = mysqli_query($con, "SELECT company_name FROM companies WHERE id = " . $active_conversation['id']);
        $other_info = mysqli_fetch_assoc($other_q);
        $active_name = $other_info['company_name'] ?? 'Company';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Message Center | NovaHire</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .mc-container {
            max-width: 1100px;
            margin: 20px auto;
        }
        .mc-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 30px 40px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mc-header h1 { font-weight: 800; font-size: 1.6rem; color: white; margin: 0; }
        .mc-body {
            display: flex;
            background: white;
            border-radius: 0 0 20px 20px;
            border: 1px solid #f1f5f9;
            border-top: none;
            min-height: 600px;
            overflow: hidden;
        }
        
        /* Sidebar */
        .mc-sidebar {
            width: 320px;
            border-right: 1px solid #f1f5f9;
            overflow-y: auto;
            flex-shrink: 0;
        }
        .mc-sidebar-search {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        .mc-sidebar-search input {
            width: 100%;
            border: 2px solid #f1f5f9;
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s;
        }
        .mc-sidebar-search input:focus { border-color: #4f46e5; }
        .mc-conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f8fafc;
            text-decoration: none;
            color: inherit;
        }
        .mc-conv-item:hover { background: #f8fafc; text-decoration: none; color: inherit; }
        .mc-conv-item.active { background: #eef2ff; border-left: 3px solid #4f46e5; }
        .mc-conv-item.unread { background: #fafbff; }
        .mc-conv-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            font-weight: 700;
        }
        .mc-conv-info { flex: 1; min-width: 0; }
        .mc-conv-info h6 { font-weight: 700; font-size: 0.85rem; margin: 0; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mc-conv-info p { font-size: 0.78rem; color: #94a3b8; margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mc-conv-time { font-size: 0.7rem; color: #94a3b8; white-space: nowrap; }
        .mc-conv-badge {
            background: #4f46e5;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Chat Area */
        .mc-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .mc-chat-header {
            padding: 16px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mc-chat-header h5 { font-weight: 700; margin: 0; font-size: 1rem; }
        .mc-chat-header small { color: #94a3b8; }
        .mc-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .mc-msg {
            max-width: 65%;
            display: flex;
            flex-direction: column;
        }
        .mc-msg.sent {
            align-self: flex-end;
        }
        .mc-msg.received {
            align-self: flex-start;
        }
        .mc-msg-bubble {
            padding: 12px 18px;
            border-radius: 16px;
            font-size: 0.9rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .mc-msg.sent .mc-msg-bubble {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .mc-msg.received .mc-msg-bubble {
            background: #f1f5f9;
            color: #0f172a;
            border-bottom-left-radius: 4px;
        }
        .mc-msg-subject {
            font-weight: 700;
            font-size: 0.8rem;
            margin-bottom: 5px;
            opacity: 0.8;
        }
        .mc-msg-time {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 5px;
        }
        .mc-msg.sent .mc-msg-time { text-align: right; }
        .mc-msg-read-status { font-size: 0.7rem; color: #94a3b8; }
        
        /* Compose */
        .mc-compose {
            padding: 20px 25px;
            border-top: 1px solid #f1f5f9;
            background: #fafbff;
        }
        .mc-compose-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        .mc-compose-input {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.9rem;
            resize: none;
            outline: none;
            min-height: 48px;
            max-height: 120px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .mc-compose-input:focus { border-color: #4f46e5; }
        .mc-send-btn {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .mc-send-btn:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(79,70,229,0.3); }
        
        .mc-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        .mc-empty i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
        .mc-empty h3 { font-weight: 700; color: #64748b; }
        
        .no-conv {
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
        }
        
        /* Compose New Modal */
        .compose-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .compose-modal-overlay.show { display: flex; }
        .compose-modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            padding: 30px;
            animation: modalSlide 0.3s ease;
        }
        @keyframes modalSlide {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
<?php include 'company_header.php'; ?>

<div class="container mc-container">
    <div class="mc-header">
        <h1><i class="fas fa-envelope mr-3"></i>Message Center</h1>
        <button class="btn btn-light rounded-pill font-weight-bold" onclick="document.getElementById('composeModal').classList.add('show')">
            <i class="fas fa-pen mr-2"></i>New Message
        </button>
    </div>
    
    <div class="mc-body">
        <!-- Sidebar: Conversations -->
        <div class="mc-sidebar">
            <div class="mc-sidebar-search">
                <input type="text" placeholder="Search conversations..." oninput="filterConversations(this.value)">
            </div>
            <div id="convList">
                <?php if (empty($conversation_list)): ?>
                    <div class="no-conv">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <p>No conversations yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversation_list as $key => $conv):
                        $active_class = ($active_conversation && $active_conversation['type'] === $conv['type'] && $active_conversation['id'] == $conv['id']) ? 'active' : '';
                        $unread_class = $conv['unread'] > 0 ? 'unread' : '';
                        $initial = strtoupper(substr($conv['name'], 0, 1));
                        $time = time_ago($conv['last_time']);
                    ?>
                        <a href="message_center.php?with=<?php echo $conv['type'] . '_' . $conv['id']; ?>" 
                           class="mc-conv-item <?php echo $active_class . ' ' . $unread_class; ?>" 
                           data-name="<?php echo htmlspecialchars(strtolower($conv['name'])); ?>">
                            <div class="mc-conv-avatar"><?php echo $initial; ?></div>
                            <div class="mc-conv-info">
                                <h6><?php echo htmlspecialchars($conv['name']); ?></h6>
                                <p><?php echo htmlspecialchars(substr($conv['last_message'], 0, 40)); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <div class="mc-conv-time"><?php echo $time; ?></div>
                                <?php if ($conv['unread'] > 0): ?>
                                    <div class="mc-conv-badge mt-1"><?php echo $conv['unread']; ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="mc-chat">
            <?php if ($active_conversation && !empty($active_name)): ?>
                <div class="mc-chat-header">
                    <div class="mc-conv-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                        <?php echo strtoupper(substr($active_name, 0, 1)); ?>
                    </div>
                    <div>
                        <h5><?php echo htmlspecialchars($active_name); ?></h5>
                        <small><?php echo ucfirst($active_conversation['type']); ?></small>
                    </div>
                </div>
                
                <div class="mc-chat-messages" id="chatMessages">
                    <?php foreach ($messages as $msg):
                        $is_sent = ($msg['sender_type'] === 'company' && $msg['sender_id'] == $company_id);
                        $class = $is_sent ? 'sent' : 'received';
                        $time = date('M d, g:i A', strtotime($msg['created_at']));
                    ?>
                        <div class="mc-msg <?php echo $class; ?>">
                            <?php if ($msg['subject']): ?>
                                <div class="mc-msg-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                            <?php endif; ?>
                            <div class="mc-msg-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            <div class="mc-msg-time">
                                <?php echo $time; ?>
                                <?php if ($is_sent): ?>
                                    <span class="mc-msg-read-status">
                                        <?php echo $msg['is_read'] ? '<i class="fas fa-check-double"></i> Read' : '<i class="fas fa-check"></i> Sent'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mc-compose">
                    <form method="POST" class="mc-compose-form" onsubmit="return validateMsgForm()">
                        <input type="hidden" name="receiver_type" value="<?php echo $active_conversation['type']; ?>">
                        <input type="hidden" name="receiver_id" value="<?php echo $active_conversation['id']; ?>">
                        <input type="hidden" name="subject" value="Re: Conversation">
                        <textarea name="message" class="mc-compose-input" placeholder="Type your message..." required id="msgInput"></textarea>
                        <button type="submit" name="send_message" class="mc-send-btn" title="Send">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="mc-empty">
                    <i class="fas fa-comments"></i>
                    <h3>Select a Conversation</h3>
                    <p>Choose a conversation from the sidebar or start a new one.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Compose New Message Modal -->
<div class="compose-modal-overlay" id="composeModal">
    <div class="compose-modal">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="font-weight-bold m-0"><i class="fas fa-pen mr-2"></i>New Message</h4>
            <button class="btn btn-sm btn-light rounded-circle" onclick="this.closest('.compose-modal-overlay').classList.remove('show')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="send_message" value="1">
            
            <div class="form-group">
                <label class="font-weight-bold small">Send To (User)</label>
                <select name="receiver_id" class="form-control" required id="newMsgReceiver" style="border-radius: 10px;">
                    <option value="">Select a user...</option>
                    <?php
                    $users_q = mysqli_query($con, "SELECT id, username FROM user_info WHERE status='active' ORDER BY username");
                    while ($usr = mysqli_fetch_assoc($users_q)):
                    ?>
                        <option value="<?php echo $usr['id']; ?>"><?php echo htmlspecialchars($usr['username']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="hidden" name="receiver_type" value="user">
            </div>
            
            <div class="form-group">
                <label class="font-weight-bold small">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="What is this about?" required style="border-radius: 10px;">
            </div>
            
            <div class="form-group">
                <label class="font-weight-bold small">Message</label>
                <textarea name="message" class="form-control" rows="4" placeholder="Write your message..." required style="border-radius: 10px;"></textarea>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4" onclick="this.closest('.compose-modal-overlay').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-paper-plane mr-2"></i>Send Message</button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($success_msg)): ?>
    <script>showToast('success', 'Success', '<?php echo $success_msg; ?>');</script>
<?php endif; ?>
<?php if (isset($error_msg)): ?>
    <script>showToast('error', 'Error', '<?php echo $error_msg; ?>');</script>
<?php endif; ?>

<script>
    // Toast notification function
    function showToast(type, title, message, duration) {
        duration = duration || 5000;
        var icons = { success: 'fa-check-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle' };
        var toast = document.createElement('div');
        toast.style.cssText = 'background:white;border-radius:12px;padding:16px 20px;box-shadow:0 10px 40px rgba(0,0,0,0.15);display:flex;align-items:flex-start;gap:12px;min-width:300px;max-width:400px;margin-bottom:10px;border-left:4px solid ' + (type==='success'?'#10b981':type==='error'?'#ef4444':'#3b82f6');
        toast.innerHTML = '<div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:' + (type==='success'?'#dcfce7':type==='error'?'#fee2e2':'#dbeafe') + ';color:' + (type==='success'?'#10b981':type==='error'?'#ef4444':'#3b82f6') + ';"><i class="fas ' + (icons[type]||icons.info) + '"></i></div><div style="flex:1;"><h6 style="font-weight:700;font-size:0.85rem;margin:0 0 3px;color:#0f172a;">' + title + '</h6><p style="font-size:0.8rem;color:#64748b;margin:0;line-height:1.4;">' + message + '</p></div><button style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;padding:0;" onclick="this.parentElement.remove()">&times;</button>';
        var container = document.getElementById('toastContainer') || (function(){ var c = document.createElement('div'); c.id='toastContainer'; c.style.cssText='position:fixed;top:80px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:10px;'; document.body.appendChild(c); return c; })();
        container.appendChild(toast);
        setTimeout(function(){ toast.style.opacity='0'; toast.style.transform='translateX(100%)'; toast.style.transition='all 0.3s ease'; setTimeout(function(){ toast.remove(); }, 300); }, duration);
    }
</script>

<script>
    // Auto-scroll chat to bottom
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Filter conversations
    function filterConversations(query) {
        const items = document.querySelectorAll('.mc-conv-item');
        query = query.toLowerCase();
        items.forEach(item => {
            const name = item.getAttribute('data-name');
            item.style.display = name.includes(query) ? 'flex' : 'none';
        });
    }
    
    // Validate message form
    function validateMsgForm() {
        const input = document.getElementById('msgInput');
        if (!input.value.trim()) {
            input.focus();
            return false;
        }
        return true;
    }
    
    // Auto-resize textarea
    const msgInput = document.getElementById('msgInput');
    if (msgInput) {
        msgInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }
</script>
</body>
</html>
