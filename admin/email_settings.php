<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $settings = [
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => intval($_POST['smtp_port'] ?? 587),
        'smtp_username' => trim($_POST['smtp_username'] ?? ''),
        'smtp_password' => trim($_POST['smtp_password'] ?? ''),
        'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? 'NovaHire'),
        'smtp_encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = mysqli_prepare($con, "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        mysqli_stmt_bind_param($stmt, "sss", $key, $value, $value);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Test email if requested
    if (isset($_POST['test_email'])) {
        $test_email = trim($_POST['test_email_to'] ?? '');
        if ($test_email) {
            if (send_email($test_email, 'NovaHire SMTP Test', '<h2>SMTP Configuration Test</h2><p>Your email is configured correctly!</p>')) {
                $success = 'Test email sent successfully to ' . htmlspecialchars($test_email);
            } else {
                $error = 'Failed to send test email. Please check your SMTP settings.';
            }
        }
    } else {
        $success = 'Email settings saved successfully!';
    }
}

// Load current settings
$current = [];
$result = mysqli_query($con, "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'");
while ($row = mysqli_fetch_assoc($result)) {
    $current[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Email Settings | NovaHire Admin</title>
    <style>
        .settings-page {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .settings-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .settings-card h3 {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #0ea5e9;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            color: white;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .test-email-section {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .test-email-section h4 {
            margin-bottom: 15px;
        }
        
        .test-email-row {
            display: flex;
            gap: 10px;
        }
        
        .test-email-row input {
            flex: 1;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="settings-page">
        <h1>Email Settings</h1>
        <p class="text-muted mb-4">Configure SMTP settings for sending emails</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <?php echo csrf_input(); ?>
            
            <div class="settings-card">
                <h3><i class="fas fa-server"></i> SMTP Server Settings</h3>
                
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" 
                           value="<?php echo e($current['smtp_host'] ?? 'smtp.gmail.com'); ?>"
                           placeholder="smtp.gmail.com">
                    <small class="text-muted">For Gmail: smtp.gmail.com | For Outlook: smtp-mail.outlook.com</small>
                </div>
                
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-control" 
                           value="<?php echo e($current['smtp_port'] ?? '587'); ?>"
                           placeholder="587">
                    <small class="text-muted">Common ports: 587 (TLS), 465 (SSL), 25 (unsecured)</small>
                </div>
                
                <div class="form-group">
                    <label>Encryption</label>
                    <select name="smtp_encryption" class="form-control">
                        <option value="tls" <?php echo ($current['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($current['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo empty($current['smtp_encryption']) ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
            </div>
            
            <div class="settings-card">
                <h3><i class="fas fa-user-lock"></i> Authentication</h3>
                
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_username" class="form-control" 
                           value="<?php echo e($current['smtp_username'] ?? ''); ?>"
                           placeholder="your-email@gmail.com">
                </div>
                
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_password" class="form-control" 
                           value="<?php echo e($current['smtp_password'] ?? ''); ?>"
                           placeholder="Your app password">
                    <small class="text-muted">For Gmail, use App Password (not your regular password)</small>
                </div>
            </div>
            
            <div class="settings-card">
                <h3><i class="fas fa-envelope"></i> Sender Settings</h3>
                
                <div class="form-group">
                    <label>From Email</label>
                    <input type="email" name="smtp_from_email" class="form-control" 
                           value="<?php echo e($current['smtp_from_email'] ?? ''); ?>"
                           placeholder="noreply@novahire.com">
                </div>
                
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="smtp_from_name" class="form-control" 
                           value="<?php echo e($current['smtp_from_name'] ?? 'NovaHire'); ?>"
                           placeholder="NovaHire">
                </div>
            </div>
            
            <div class="settings-card">
                <div class="test-email-section">
                    <h4><i class="fas fa-paper-plane"></i> Test Email Configuration</h4>
                    <p class="text-muted">Send a test email to verify your SMTP settings</p>
                    <div class="test-email-row">
                        <input type="email" name="test_email_to" class="form-control" 
                               placeholder="Enter email to test">
                        <button type="submit" name="test_email" value="1" class="btn btn-secondary">
                            <i class="fas fa-paper-plane"></i> Send Test
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>
</body>
</html>
