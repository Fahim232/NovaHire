<?php
/**
 * NovaHire — Professional Email System v2
 *
 * Features:
 * - PHPMailer SMTP integration (Composer autoload)
 * - Email logging to email_logs table
 * - Notification preferences check
 * - Retry on failure
 * - Branded HTML templates
 * - Fallback to PHP mail() if SMTP unavailable
 */

if (defined('NOVAHIRE_MAIL')) return;
define('NOVAHIRE_MAIL', true);

/* ═══ PHPMailer Autoload ═══ */
$mailer_loaded = false;
$autoload_paths = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
];
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $mailer_loaded = true;
        break;
    }
}

/* ═══ SMTP Config ═══ */
function get_smtp_config() {
    global $con;
    if (isset($con)) {
        $result = @mysqli_query($con, "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'");
        if ($result && mysqli_num_rows($result) > 0) {
            $config = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $config[$row['setting_key']] = $row['setting_value'];
            }
            if (!empty($config['smtp_host'])) {
                return [
                    'host'       => $config['smtp_host'] ?? 'smtp.gmail.com',
                    'port'       => intval($config['smtp_port'] ?? 587),
                    'username'   => $config['smtp_username'] ?? '',
                    'password'   => $config['smtp_password'] ?? '',
                    'from_email' => $config['smtp_from_email'] ?? $config['smtp_username'] ?? '',
                    'from_name'  => $config['smtp_from_name'] ?? 'NovaHire',
                    'encryption' => $config['smtp_encryption'] ?? 'tls',
                ];
            }
        }
    }
    return [
        'host'       => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
        'port'       => defined('SMTP_PORT') ? SMTP_PORT : 587,
        'username'   => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
        'password'   => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '',
        'from_name'  => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NovaHire',
        'encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
    ];
}

function smtp_is_configured() {
    $c = get_smtp_config();
    return !empty($c['username']) && !empty($c['password']);
}

/* ═══ Create PHPMailer ═══ */
function create_mailer() {
    global $mailer_loaded;
    if (!$mailer_loaded || !smtp_is_configured()) return null;
    $config = get_smtp_config();
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        return $mail;
    } catch (Exception $e) {
        error_log("NovaHire Mailer creation failed: " . $e->getMessage());
        return null;
    }
}

/* ═══ Log Email ═══ */
function log_email($to, $subject, $template, $status, $error = null, $attempts = 1) {
    global $con;
    if (!isset($con)) return;
    $stmt = @mysqli_prepare($con, "INSERT INTO email_logs (recipient_email, subject, template, status, error_message, attempts) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssi", $to, $subject, $template, $status, $error, $attempts);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/* ═══ Core Send ═══ */
function send_email($to, $subject, $html_body, $text_body = '', $template = 'general') {
    $mail = create_mailer();
    if ($mail === null) {
        $ok = send_email_fallback($to, $subject, $html_body);
        log_email($to, $subject, $template, $ok ? 'sent' : 'failed', $ok ? null : 'Fallback mail() failed');
        return $ok;
    }
    try {
        $mail->clearAddresses();
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $text_body ?: strip_tags($html_body);
        $mail->send();
        log_email($to, $subject, $template, 'sent');
        return true;
    } catch (Exception $e) {
        error_log("NovaHire email failed to $to: " . $e->getMessage());
        $ok = send_email_fallback($to, $subject, $html_body);
        log_email($to, $subject, $template, $ok ? 'sent' : 'failed', $e->getMessage(), 2);
        return $ok;
    }
}

function send_email_fallback($to, $subject, $html_body) {
    $config = get_smtp_config();
    $from_name = $config['from_name'] ?? 'NovaHire';
    $from_email = $config['from_email'] ?? 'noreply@novahire.com';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "X-Mailer: NovaHire/" . phpversion() . "\r\n";
    return @mail($to, $subject, $html_body, $headers);
}

/* ═══ Notification Preferences ═══ */
function email_pref_enabled($user_id, $user_type, $pref_key) {
    global $con;
    if (!isset($con)) return true;
    $stmt = @mysqli_prepare($con, "SELECT {$pref_key} FROM notification_preferences WHERE user_id=? AND user_type=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $user_type);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($stmt);
            return (int)$row[$pref_key] === 1;
        }
        mysqli_stmt_close($stmt);
    }
    return true;
}

function get_user_email($user_id, $user_type = 'user') {
    global $con;
    if (!isset($con)) return null;
    if ($user_type === 'company') {
        $r = @mysqli_query($con, "SELECT company_email FROM companies WHERE id=" . intval($user_id) . " LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) return $row['company_email'];
    } elseif ($user_type === 'user') {
        $r = @mysqli_query($con, "SELECT email FROM user_info WHERE id=" . intval($user_id) . " LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) return $row['email'];
    }
    return null;
}

/* ═══ Email Templates ═══ */
function email_header($title = '') {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.06);">
<tr><td style="background:linear-gradient(135deg,#3b82f6 0%,#06b6d4 50%,#0ea5e9 100%);padding:32px 40px;text-align:center;">
<h1 style="color:#fff;margin:0;font-size:22px;font-weight:800;letter-spacing:-.3px;">NovaHire</h1>'
. ($title ? '<p style="color:rgba(255,255,255,.88);margin:8px 0 0;font-size:13px;font-weight:500;">' . $title . '</p>' : '') .
'</td></tr>';
}

function email_footer() {
    $y = date('Y');
    return '<tr><td style="padding:24px 40px;text-align:center;border-top:1px solid #e2e8f0;">
<p style="color:#94a3b8;font-size:11px;margin:0;">&copy; ' . $y . ' NovaHire. All rights reserved.</p>
<p style="color:#cbd5e1;font-size:10px;margin:6px 0 0;">This is an automated email from NovaHire. Please do not reply.</p>
</td></tr></table></td></tr></table></body></html>';
}

function email_body_wrapper($content) {
    return '<tr><td style="padding:36px 40px;">' . $content . '</td></tr>';
}

function email_btn($url, $text) {
    return '<a href="' . $url . '" style="display:inline-block;background:linear-gradient(135deg,#3b82f6,#06b6d4);color:#fff;padding:13px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;margin-top:12px;box-shadow:0 4px 14px rgba(59,130,246,.3);">' . $text . '</a>';
}

function email_info_box($label, $value) {
    return '<p style="margin:0 0 4px;color:#64748b;font-size:13px;"><strong style="color:#1e293b;">' . $label . ':</strong> ' . $value . '</p>';
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Welcome Email
   ═══════════════════════════════════════════════════════════════ */
function send_welcome_email($to, $username) {
    $subject = "Welcome to NovaHire!";
    $body = email_header("Welcome aboard!") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Hello ' . htmlspecialchars($username) . '!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Welcome to NovaHire! Your account has been created and you\'re ready to start your career journey.
</p>
<div style="background:#f8fafc;border-radius:12px;padding:20px;margin:18px 0;border:1px solid #e2e8f0;">
<h3 style="color:#3b82f6;margin:0 0 10px;font-size:15px;">Getting Started:</h3>
<ul style="color:#475569;font-size:13px;line-height:2;margin:0;padding-left:18px;">
<li>Complete your profile to attract recruiters</li>
<li>Browse and apply to matching jobs</li>
<li>Take skill assessments to prove your expertise</li>
<li>Use AI tools for resume analysis and cover letters</li>
</ul></div>
' . email_btn(BASE_URL . '/seeker/seeker_dashboard.php', 'Go to Dashboard')) . email_footer();
    return send_email($to, $subject, $body, '', 'welcome');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Password Reset
   ═══════════════════════════════════════════════════════════════ */
function send_password_reset_email($to, $reset_code, $user_type = 'user') {
    $subject = "Password Reset Code - NovaHire";
    $body = email_header("Password Reset") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Password Reset Request</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
We received a request to reset your password. Use the code below.
</p>
<div style="background:linear-gradient(135deg,#3b82f6,#06b6d4);border-radius:12px;padding:28px;text-align:center;margin:20px 0;">
<p style="color:rgba(255,255,255,.85);margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Your Reset Code</p>
<p style="color:#fff;font-size:34px;font-weight:800;letter-spacing:10px;margin:0;">' . $reset_code . '</p>
<p style="color:rgba(255,255,255,.65);margin:10px 0 0;font-size:11px;">Valid for 1 hour</p>
</div>
<p style="color:#94a3b8;font-size:12px;margin:18px 0 0;">
If you didn\'t request this, ignore this email. Your password will remain unchanged.
</p>') . email_footer();
    return send_email($to, $subject, $body, '', 'password_reset');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Application Confirmation
   ═══════════════════════════════════════════════════════════════ */
function send_application_confirmation($to, $username, $job_title, $company_name) {
    $subject = "Application Submitted - {$job_title} at {$company_name}";
    $body = email_header("Application Confirmation") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Application Submitted!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Hello <strong>' . htmlspecialchars($username) . '</strong>, your application has been submitted successfully.
</p>
<div style="background:#eff6ff;border-left:4px solid #3b82f6;border-radius:0 12px 12px 0;padding:18px;margin:18px 0;">
' . email_info_box("Position", htmlspecialchars($job_title)) .
email_info_box("Company", htmlspecialchars($company_name)) .
email_info_box("Applied", date('M d, Y')) . '
</div>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:18px 0 0;">
Track your application status from your dashboard. Good luck!
</p>
' . email_btn(BASE_URL . '/seeker/my_application.php', 'Track Application')) . email_footer();
    return send_email($to, $subject, $body, '', 'application_confirmation');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Interview Scheduled
   ═══════════════════════════════════════════════════════════════ */
function send_interview_scheduled($to, $username, $job_title, $company_name, $date, $time, $type, $meeting_link = '') {
    $subject = "Interview Scheduled - {$job_title} at {$company_name}";
    $body = email_header("Interview Scheduled") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Interview Scheduled!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Hello <strong>' . htmlspecialchars($username) . '</strong>, your interview has been scheduled.
</p>
<div style="background:#f0fdf4;border-left:4px solid #059669;border-radius:0 12px 12px 0;padding:18px;margin:18px 0;">
' . email_info_box("Position", htmlspecialchars($job_title)) .
email_info_box("Company", htmlspecialchars($company_name)) .
email_info_box("Date", htmlspecialchars($date)) .
email_info_box("Time", htmlspecialchars($time)) .
email_info_box("Type", htmlspecialchars($type)) .
($meeting_link ? email_info_box("Link", '<a href="' . htmlspecialchars($meeting_link) . '" style="color:#3b82f6;">Join Meeting</a>') : '') . '
</div>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:18px 0 0;">
Please be available at the scheduled time. Good luck!
</p>') . email_footer();
    return send_email($to, $subject, $body, '', 'interview_scheduled');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: New Application Alert (to Company)
   ═══════════════════════════════════════════════════════════════ */
function send_new_application_alert($to, $company_name, $applicant_name, $job_title) {
    $subject = "New Application Received - {$job_title}";
    $body = email_header("New Application") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">New Application Received!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Hello <strong>' . htmlspecialchars($company_name) . '</strong>, a new application has been submitted.
</p>
<div style="background:#fef3c7;border-left:4px solid #d97706;border-radius:0 12px 12px 0;padding:18px;margin:18px 0;">
' . email_info_box("Applicant", htmlspecialchars($applicant_name)) .
email_info_box("Position", htmlspecialchars($job_title)) .
email_info_box("Date", date('M d, Y h:i A')) . '
</div>
' . email_btn(BASE_URL . '/company/view_applicants.php', 'View Applications')) . email_footer();
    return send_email($to, $subject, $body, '', 'new_application_alert');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Job Alert
   ═══════════════════════════════════════════════════════════════ */
function send_job_alert($to, $username, $job) {
    $subject = "New Job Match: " . ($job['job_title'] ?? '');
    $body = email_header("New Job Alert") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">New Job Match Found!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Hello <strong>' . htmlspecialchars($username) . '</strong>, a new job matching your skills has been posted.
</p>
<div style="background:#eff6ff;border-radius:12px;padding:20px;margin:18px 0;border:1px solid #dbeafe;">
<h3 style="color:#1e293b;margin:0 0 10px;font-size:17px;">' . htmlspecialchars($job['job_title'] ?? '') . '</h3>
' . email_info_box("Category", htmlspecialchars($job['job_category'] ?? '')) .
email_info_box("Location", htmlspecialchars($job['location'] ?? '')) .
email_info_box("Type", htmlspecialchars($job['employment_type'] ?? '')) .
(!empty($job['salary_range']) ? email_info_box("Salary", htmlspecialchars($job['salary_range'])) : '') . '
</div>
' . email_btn(BASE_URL . '/seeker/job_details.php?id=' . ($job['job_id'] ?? ''), 'Apply Now')) . email_footer();
    return send_email($to, $subject, $body, '', 'job_alert');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Weekly Digest
   ═══════════════════════════════════════════════════════════════ */
function send_weekly_job_digest($to, $username, $jobs) {
    $subject = "Weekly Job Digest - " . count($jobs) . " New Jobs for You";
    $jobs_html = '';
    foreach (array_slice($jobs, 0, 5) as $job) {
        $title = htmlspecialchars($job['job_title'] ?? '');
        $company = htmlspecialchars($job['company_name'] ?? 'Company');
        $location = htmlspecialchars($job['location'] ?? 'Remote');
        $id = intval($job['job_id'] ?? $job['id'] ?? 0);
        $jobs_html .= '<div style="background:#f8fafc;border-radius:10px;padding:16px;margin:10px 0;border:1px solid #e2e8f0;">
<h4 style="color:#1e293b;margin:0 0 6px;font-size:14px;">' . $title . '</h4>
<p style="color:#64748b;margin:0 0 8px;font-size:12px;">' . $company . ' &bull; ' . $location . '</p>
<a href="' . BASE_URL . '/seeker/job_details.php?id=' . $id . '" style="color:#3b82f6;font-size:12px;font-weight:700;text-decoration:none;">View Details</a>
</div>';
    }
    $body = email_header("Your Weekly Job Digest") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Hello ' . htmlspecialchars($username) . '!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Here are this week\'s top job matches for you based on your skills and preferences.
</p>
' . $jobs_html . '
' . email_btn(BASE_URL . '/seeker/browse_jobs.php', 'Browse All Jobs')) . email_footer();
    return send_email($to, $subject, $body, '', 'weekly_digest');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Company Welcome
   ═══════════════════════════════════════════════════════════════ */
function send_company_welcome_email($to, $company_name) {
    $subject = "Welcome to NovaHire - Employer Portal";
    $body = email_header("Welcome, Employer!") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">Hello ' . htmlspecialchars($company_name) . '!</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
Your employer account has been created. You can now post jobs, review applications, and find the best talent.
</p>
<div style="background:#f0fdf4;border-radius:12px;padding:20px;margin:18px 0;border:1px solid #bbf7d0;">
<h3 style="color:#059669;margin:0 0 10px;font-size:15px;">Next Steps:</h3>
<ul style="color:#475569;font-size:13px;line-height:2;margin:0;padding-left:18px;">
<li>Complete your company profile</li>
<li>Post your first job listing</li>
<li>Review incoming applications</li>
</ul></div>
' . email_btn(BASE_URL . '/company/company_dashboard.php', 'Go to Dashboard')) . email_footer();
    return send_email($to, $subject, $body, '', 'company_welcome');
}

/* ═══════════════════════════════════════════════════════════════
   TEMPLATE: Mentor Application Received (Admin Alert)
   ═══════════════════════════════════════════════════════════════ */
function send_mentor_application_alert($to, $mentor_name, $mentor_email, $category) {
    $subject = "New Mentor Application - {$mentor_name}";
    $body = email_header("Mentor Application") . email_body_wrapper('
<h2 style="color:#1e293b;margin:0 0 14px;font-size:19px;">New Mentor Application</h2>
<p style="color:#475569;font-size:14px;line-height:1.75;margin:0 0 18px;">
A new mentor has applied to join NovaHire. Review their application.
</p>
<div style="background:#fdf4ff;border-left:4px solid #38bdf8;border-radius:0 12px 12px 0;padding:18px;margin:18px 0;">
' . email_info_box("Name", htmlspecialchars($mentor_name)) .
email_info_box("Email", htmlspecialchars($mentor_email)) .
email_info_box("Category", htmlspecialchars($category)) . '
</div>
' . email_btn(BASE_URL . '/admin/mentors.php', 'Review Application')) . email_footer();
    return send_email($to, $subject, $body, '', 'mentor_application');
}
