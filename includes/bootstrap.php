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
    session_start();
}

/* ── 3. Database ──────────────────────────────────────────────────────────── */
if (!isset($con)) {
    require_once __DIR__ . '/../admin/dbcon.php';
}

/* ── 4. Shared helpers ────────────────────────────────────────────────────── */
if (!function_exists('create_notification')) {
    require_once __DIR__ . '/functions.php';
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
        header('Location: ' . BASE_URL . '/company_login.php');
        exit;
    }
}

function require_admin_login() {
    if (!isset($_SESSION['admin_username'])) {
        header('Location: ' . BASE_URL . '/admin/admin_login.php');
        exit;
    }
}
