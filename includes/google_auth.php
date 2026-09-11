<?php
/**
 * NovaHire — Google OAuth Integration
 * Social login with Google
 */

if (defined('NOVAHIRE_GOOGLE_AUTH')) return;
define('NOVAHIRE_GOOGLE_AUTH', true);

/* ── Configuration ──────────────────────────────────────────────────────── */
function get_google_config() {
    return [
        'client_id'     => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
        'client_secret' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '',
        'redirect_uri'  => defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : BASE_URL . '/auth/google_callback.php',
    ];
}

/* ── Generate Google Auth URL ───────────────────────────────────────────── */
function get_google_auth_url() {
    $config = get_google_config();
    
    $params = http_build_query([
        'client_id'     => $config['client_id'],
        'redirect_uri'  => $config['redirect_uri'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
    ]);
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

/* ── Exchange Code for Tokens ───────────────────────────────────────────── */
function exchange_google_code($code) {
    $config = get_google_config();
    
    $data = [
        'code'          => $code,
        'client_id'     => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri'  => $config['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/* ── Get User Info from Google ──────────────────────────────────────────── */
function get_google_user_info($access_token) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/* ── Handle Google Login/Register ───────────────────────────────────────── */
function handle_google_login($con, $google_user) {
    $google_id = $google_user['id'] ?? '';
    $email = $google_user['email'] ?? '';
    $name = $google_user['name'] ?? '';
    $picture = $google_user['picture'] ?? '';
    
    if (empty($google_id) || empty($email)) {
        return ['success' => false, 'message' => 'Invalid Google account data'];
    }
    
    // Check if user exists by google_id
    $stmt = mysqli_prepare($con, "SELECT id, username, email, profile FROM user_info WHERE google_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $google_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Existing Google user - login
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        create_activity_log('user', $user['id'], 'google_login');
        
        return ['success' => true, 'user' => $user];
    }
    mysqli_stmt_close($stmt);
    
    // Check if user exists by email
    $stmt = mysqli_prepare($con, "SELECT id, username, email, profile, google_id FROM user_info WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Existing email user - link Google account
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $update_stmt = mysqli_prepare($con, "UPDATE user_info SET google_id = ?, email_verified = 1 WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "si", $google_id, $user['id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        create_activity_log('user', $user['id'], 'google_linked');
        
        return ['success' => true, 'user' => $user, 'linked' => true];
    }
    mysqli_stmt_close($stmt);
    
    // New user - create account
    $username = $name;
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    
    $insert_stmt = mysqli_prepare($con, "INSERT INTO user_info (username, email, password, cpassword, google_id, email_verified, profile) VALUES (?, ?, ?, ?, ?, 1, ?)");
    mysqli_stmt_bind_param($insert_stmt, "ssssss", $username, $email, $password, $password, $google_id, $picture);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $user_id = mysqli_insert_id($con);
        mysqli_stmt_close($insert_stmt);
        
        $_SESSION['id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        // Send welcome email
        send_welcome_email($email, $username);
        
        create_activity_log('user', $user_id, 'google_register');
        
        return ['success' => true, 'user' => ['id' => $user_id, 'username' => $username, 'email' => $email], 'new_user' => true];
    }
    mysqli_stmt_close($insert_stmt);
    
    return ['success' => false, 'message' => 'Failed to create account'];
}

/* ── Render Google Login Button ─────────────────────────────────────────── */
function render_google_login_button($text = 'Continue with Google') {
    $auth_url = get_google_auth_url();
    
    return '
    <a href="' . htmlspecialchars($auth_url) . '" class="google-login-btn">
        <svg width="20" height="20" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        ' . $text . '
    </a>';
}

/* ── Render Social Login Buttons ────────────────────────────────────────── */
function render_social_login_buttons() {
    return '
    <div class="social-login-divider">
        <span>or continue with</span>
    </div>
    <div class="social-login-buttons">
        ' . render_google_login_button() . '
        <button class="social-login-btn linkedin" onclick="linkedinLogin()">
            <i class="fab fa-linkedin-in"></i>
            LinkedIn
        </button>
    </div>';
}
?>
