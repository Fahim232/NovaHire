<?php
/**
 * NovaHire — Security Module
 * CSRF protection, rate limiting, input sanitization, security headers
 */

if (defined('NOVAHIRE_SECURITY')) return;
define('NOVAHIRE_SECURITY', true);

/* ── CSRF Token ──────────────────────────────────────────────────────────── */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token mismatch. Please refresh and try again.']);
            } else {
                echo '<script>alert("Security error. Please refresh the page and try again."); window.history.back();</script>';
            }
            exit;
        }
    }
}

/* ── Rate Limiting ───────────────────────────────────────────────────────── */
function check_rate_limit($action, $max_attempts = 5, $window_seconds = 300) {
    $key = 'rate_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $now = time();
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
    }
    
    $data = &$_SESSION[$key];
    
    // Reset if window expired
    if ($now - $data['first_attempt'] > $window_seconds) {
        $data = ['count' => 0, 'first_attempt' => $now];
    }
    
    $data['count']++;
    
    if ($data['count'] > $max_attempts) {
        $retry_after = $window_seconds - ($now - $data['first_attempt']);
        http_response_code(429);
        header("Retry-After: $retry_after");
        return false;
    }
    
    return true;
}

function rate_limit_response($action) {
    if (!check_rate_limit($action)) {
        $message = "Too many attempts. Please wait a few minutes before trying again.";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message, 'retry_after' => 300]);
        } else {
            echo "<script>alert('$message'); window.history.back();</script>";
        }
        exit;
    }
}

/* ── Input Sanitization ──────────────────────────────────────────────────── */
function sanitize_string($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitize_email($input) {
    $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    return $email;
}

function sanitize_int($input) {
    return filter_var($input, FILTER_VALIDATE_INT);
}

function sanitize_url($input) {
    return filter_var($input, FILTER_SANITIZE_URL);
}

function sanitize_phone($input) {
    $cleaned = preg_replace('/[^0-9+\-\(\)\s]/', '', trim($input));
    return $cleaned;
}

function sanitize_name($input) {
    $cleaned = preg_replace('/[^a-zA-Z\s\'\-]/', '', trim($input));
    return ucwords(strtolower($cleaned));
}

function validate_input_length($input, $min = 1, $max = 255) {
    $length = strlen(trim($input));
    return $length >= $min && $length <= $max;
}

/* ── Output Escaping ─────────────────────────────────────────────────────── */
function e($input) {
    return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
}

function ej($input) {
    return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
}

/* ── Security Headers ────────────────────────────────────────────────────── */
function set_security_headers() {
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: http: https:; frame-src https://meet.jit.si; connect-src 'self';");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    }
}

/* ── Password Strength Validation ────────────────────────────────────────── */
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/* ── File Upload Security ────────────────────────────────────────────────── */
function validate_file_upload($file, $allowed_types = [], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed (error code: " . $file['error'] . ")";
        return $errors;
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "File size exceeds maximum allowed (" . ($max_size / 1048576) . "MB)";
    }
    
    if (!empty($allowed_types)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types)) {
            $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowed_types);
        }
        
        // Verify MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed_mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
        ];
        
        if (isset($allowed_mimes[$ext]) && $mime !== $allowed_mimes[$ext]) {
            $errors[] = "File content doesn't match extension";
        }
    }
    
    return $errors;
}

function generate_safe_filename($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $safe_name = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
    $safe_name = substr($safe_name, 0, 50);
    return $safe_name . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

/* ── Hardened upload handling ─────────────────────────────────────────────────
 * ONE implementation every upload handler should call, so no single page can
 * drift back to trusting the browser.
 *
 * Why the old per-page checks were unsafe:
 *   - they trusted $_FILES[...]['type'], which is just a request header the
 *     client sets — `Content-Type: image/png` on a .php file passes trivially;
 *   - they took the saved extension from the attacker-supplied filename;
 *   - they wrote into a web-served folder, so the result was executable.
 *
 * nh_upload_kinds() defines the allowlists. Validation is by real file content
 * (finfo) AND extension, the stored name is random, and a hardening .htaccess
 * is dropped into the destination folder as defence in depth.
 *
 * Returns: ['ok' => true, 'filename' => 'abc.png'] | ['ok' => false, 'error' => '...']
 */
function nh_upload_kinds() {
    return [
        'image' => [
            'exts'  => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max'   => 5 * 1024 * 1024,
            'label' => 'JPG, PNG, GIF or WebP image',
        ],
        'document' => [
            'exts'  => ['pdf', 'doc', 'docx'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                // .doc/.docx are ZIP/OLE containers; finfo often reports these:
                'application/zip',
                'application/octet-stream',
                'application/CDFV2',
            ],
            'max'   => 5 * 1024 * 1024,
            'label' => 'PDF, DOC or DOCX document',
        ],
    ];
}

/**
 * Write a .htaccess into an upload dir that stops the web server executing
 * anything inside it. This is the control that turns "uploaded a shell" from
 * remote code execution into an inert file.
 */
function nh_protect_upload_dir($dir) {
    $htaccess = rtrim($dir, '/') . '/.htaccess';
    if (file_exists($htaccess)) return;
    $rules = <<<HT
# Uploaded files are DATA, never code. Do not remove.
php_flag engine off
AddType text/plain .php .php3 .php4 .php5 .php7 .phtml .phps .pl .py .cgi .asp .aspx .sh .shtml
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
<FilesMatch "\.(?i:php|php3|php4|php5|php7|phtml|phps|pl|py|cgi|asp|aspx|sh|shtml|htaccess)$">
    Require all denied
</FilesMatch>
HT;
    @file_put_contents($htaccess, $rules);
}

/**
 * Validate and store an uploaded file safely.
 *
 * @param array  $file   One entry from $_FILES
 * @param string $dir    Absolute destination directory
 * @param string $kind   'image' | 'document'
 * @param string $prefix Optional filename prefix (e.g. 'logo', 'cv')
 */
function nh_store_upload($file, $dir, $kind = 'image', $prefix = 'file') {
    $kinds = nh_upload_kinds();
    if (!isset($kinds[$kind])) {
        return ['ok' => false, 'error' => 'Internal upload configuration error.'];
    }
    $rule = $kinds[$kind];

    if (!isset($file) || !is_array($file) || !isset($file['error'])) {
        return ['ok' => false, 'error' => 'No file was received.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was selected.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    // Confirms the file really came through PHP's upload machinery, not a path
    // the caller was tricked into passing.
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if ($file['size'] <= 0) {
        return ['ok' => false, 'error' => 'The file is empty.'];
    }
    if ($file['size'] > $rule['max']) {
        return ['ok' => false, 'error' => 'File is too large (max ' . (int)($rule['max'] / 1048576) . 'MB).'];
    }

    // Extension allowlist — decided server-side, from a fixed list.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $rule['exts'], true)) {
        return ['ok' => false, 'error' => 'Please upload a ' . $rule['label'] . '.'];
    }

    // Real content check — this is what the old code was missing.
    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    if ($mime !== null && $mime !== false && !in_array($mime, $rule['mimes'], true)) {
        return ['ok' => false, 'error' => 'The file contents do not match a ' . $rule['label'] . '.'];
    }

    // For images, insist it actually decodes as one.
    if ($kind === 'image') {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'That file is not a readable image.'];
        }
    }

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable.'];
    }
    nh_protect_upload_dir($dir);

    // Random server-side name: no attacker-controlled path or basename survives.
    $safe_prefix = preg_replace('/[^a-z0-9_]/i', '', (string)$prefix) ?: 'file';
    $filename    = $safe_prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target      = rtrim($dir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Could not save the file. Check folder permissions.'];
    }
    @chmod($target, 0644);

    return ['ok' => true, 'filename' => $filename, 'path' => $target];
}

/* ── Avatar storage: ONE canonical location ───────────────────────────────────
 * Profile photos used to be written to three different folders (auth/images/,
 * seeker/images/, images/) while each page read from only one — so a photo
 * uploaded at registration was invisible everywhere else. Everything now
 * stores to, and reads from, the project-root images/ folder.
 */
function nh_avatar_dir() {
    return dirname(__DIR__) . '/images';
}

/** Public URL for a stored avatar filename, with a default fallback. */
function nh_avatar_url($profile) {
    $base = (defined('BASE_URL') ? BASE_URL : '') . '/images/';
    $name = basename((string)$profile);   // a stored value can never escape the folder
    if ($name === '' || !is_file(nh_avatar_dir() . '/' . $name)) {
        return $base . 'default-avatar.png';
    }
    return $base . rawurlencode($name);
}

/* ── Session Security ────────────────────────────────────────────────────── */
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

/* ── Login Attempt Tracking ──────────────────────────────────────────────── */
function track_login_attempt($identifier, $success = false) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $key = 'login_attempts_' . md5($identifier);
    
    if ($success) {
        unset($_SESSION[$key]);
        return true;
    }
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$key]['count']++;
    
    // Lock after 5 attempts for 15 minutes
    if ($_SESSION[$key]['count'] >= 5) {
        $elapsed = time() - $_SESSION[$key]['first_attempt'];
        if ($elapsed < 900) {
            return false; // Locked
        } else {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        }
    }
    
    return true;
}

function is_login_locked($identifier) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) return false;
    
    $data = $_SESSION[$key];
    if ($data['count'] >= 5) {
        $elapsed = time() - $data['first_attempt'];
        if ($elapsed < 900) {
            return true; // Still locked
        } else {
            unset($_SESSION[$key]);
        }
    }
    
    return false;
}
?>
