<?php
/**
 * NovaHire — Core Bootstrap
 * Centralised setup so pages stop repeating boilerplate:
 *   1. BASE_URL  — absolute URL path to the project (works from any folder depth)
 *   2. Session   — starts a PHP session once
 *   3. Database  — loads the global $con connection (admin/dbcon.php)
 *   4. Auth guard helpers — require_seeker_login(), require_admin_login()
 *
 * Usage (top of every page):
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 *   require_seeker_login();
 */

if (defined('NOVAHIRE_BOOTSTRAP')) return;
define('NOVAHIRE_BOOTSTRAP', true);

/* ── 1. BASE_URL ────────────────────────────────────────────────────────────
 * Absolute URL path to the project root, e.g. "/Job-portal-and-grooming".
 * Works regardless of how deep the current page is (root, seeker/, auth/, ...)
 * so asset links and cross-folder links never break when pages are moved.
 */
function app_base_url() {
    static $base = null;
    if ($base === null) {
        $docroot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '.')), '/');
        $appdir  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/'); // this file lives in includes/ -> project root
        if ($docroot && $appdir !== $docroot && strpos($appdir, $docroot) === 0) {
            $base = substr($appdir, strlen($docroot));
        } else {
            $base = '';
        }
    }
    return $base;
}
if (!defined('BASE_URL')) define('BASE_URL', app_base_url());

/* ── 2. Session ───────────────────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_strict_mode', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/* ── 2b. Error display (production-safe defaults) ─────────────────────────── */
if (!defined('NOVAHIRE_DEBUG') || !NOVAHIRE_DEBUG) {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

/* ── 3. Database ──────────────────────────────────────────────────────────── */
if (!isset($con)) {
    require_once __DIR__ . '/../admin/dbcon.php';
}

/* ── 4. Security ──────────────────────────────────────────────────────────── */
require_once __DIR__ . '/security.php';
if (!headers_sent()) {
    set_security_headers();
}

/* ── 5. Shared helpers ────────────────────────────────────────────────────── */
if (!function_exists('create_notification')) {
    require_once __DIR__ . '/functions.php';
}

/* ── 6. Email System ──────────────────────────────────────────────────────── */
if (!function_exists('send_email')) {
    require_once __DIR__ . '/mail.php';
}

/* ── 7. Payment System ────────────────────────────────────────────────────── */
if (!function_exists('get_subscription_plans')) {
    require_once __DIR__ . '/payment.php';
}

/* ── 7b. Monetization (Pro plans, generalized payments, fulfilment) ─────────── */
if (!function_exists('nh_pricing')) {
    require_once __DIR__ . '/monetization.php';
}

/* ── 7c. Placement engine (recommendations, pipeline, placements) ───────────── */
if (!function_exists('nh_get_recommendations') && file_exists(__DIR__ . '/placement.php')) {
    require_once __DIR__ . '/placement.php';
}

/* ── 7d. Live grooming sessions (mentor marketplace) ────────────────────────── */
if (!function_exists('nh_confirm_session') && file_exists(__DIR__ . '/sessions.php')) {
    require_once __DIR__ . '/sessions.php';
}

/* ── 7e. Certificates (verifiable skill certificates) ───────────────────────── */
if (!function_exists('nh_issue_certificate') && file_exists(__DIR__ . '/certificates.php')) {
    require_once __DIR__ . '/certificates.php';
}

/* ── 8. Advanced Search ───────────────────────────────────────────────────── */
if (!function_exists('execute_job_search')) {
    require_once __DIR__ . '/search.php';
}

/* ── 9. Resume Builder ───────────────────────────────────────────────────── */
if (!function_exists('get_resume_data')) {
    require_once __DIR__ . '/resume_builder.php';
}

/* ── 5. Auth guards ───────────────────────────────────────────────────────── */
function require_seeker_login() {
    if (!isset($_SESSION['id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_company_login() {
    if (!isset($_SESSION['company_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_mentor_login() {
    if (!isset($_SESSION['mentor_id'])) {
        header('Location: ' . BASE_URL . '/mentor/login.php');
        exit;
    }
}

function require_admin_login() {
    if (!isset($_SESSION['admin_username'])) {
        header('Location: ' . BASE_URL . '/admin/admin_login.php');
        exit;
    }
}
