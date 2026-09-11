<?php
/**
 * NovaHire — Resume Preview Endpoint
 * Serves resume HTML directly (not via iframe srcdoc)
 * Used by resume_builder.php for live preview
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { http_response_code(401); exit; }

$user_id = intval($_GET['uid'] ?? $_SESSION['id']);
$template = $_GET['template'] ?? 'professional';

// Only allow own resume
if ($user_id !== intval($_SESSION['id'])) { http_response_code(403); exit; }

$data = get_resume_data($con, $user_id);
$html = render_resume_template($data, $template);

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
echo $html;
