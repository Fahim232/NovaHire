<?php
/**
 * AI Job Description Generator API
 * POST: { title, category, skills } -> generates description, requirements, responsibilities
 */
session_start();
if (!isset($_SESSION['company_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Not authorised']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

require_once __DIR__ . '/../ai/config.php';
require_once __DIR__ . '/../ai/engine.php';

$title   = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$skills  = trim($_POST['skills'] ?? '');

if ($title === '') {
    echo json_encode(['ok' => false, 'error' => 'Job title is required']);
    exit;
}

$prompt = "Write a professional job posting as plain text with three clearly separated sections "
        . "headed 'Description:', 'Requirements:' (bullet list), and 'Responsibilities:' (bullet list).\n"
        . "Job title: $title\n"
        . "Category: " . ($category ?: 'General') . "\n"
        . "Required skills: " . ($skills ?: 'not specified') . "\n"
        . "Keep each section concise and realistic.";

$text = '';
$llm_used = false;
if (ai_llm_available()) {
    $res = ai_llm_chat('You are an experienced technical recruiter.', $prompt, 600);
    if ($res['ok']) { $text = $res['text']; $llm_used = true; }
}

if ($text === '') {
    // Offline template fallback
    $skills_line = $skills !== '' ? $skills : 'core ' . strtolower($category) . ' technologies';
    $text = "Description:\n"
        . "We are looking for a talented $title to join our growing team. You will work with modern tools "
        . "and technologies to build reliable, high-quality products that delight our users.\n"
        . "\nRequirements:\n"
        . "- Proven experience in " . strtolower($category ?: $title) . " development\n"
        . "- Strong knowledge of " . $skills_line . "\n"
        . "- Good communication and teamwork skills\n"
        . "- Ability to solve problems independently\n"
        . "\nResponsibilities:\n"
        . "- Design, develop and maintain software solutions\n"
        . "- Collaborate with cross-functional teams to deliver features\n"
        . "- Write clean, maintainable and well-tested code\n"
        . "- Participate in code reviews and technical discussions\n";
}

// Split into the three sections
$description = $requirements = $responsibilities = '';

if (preg_match('/Description:\s*(.*?)(?=Requirements:|$)/is', $text, $m)) $description = trim($m[1]);
if (preg_match('/Requirements:\s*(.*?)(?=Responsibilities:|$)/is', $text, $m)) $requirements = trim($m[1]);
if (preg_match('/Responsibilities:\s*(.*?)(?=$)/is', $text, $m)) $responsibilities = trim($m[1]);

if ($description === '' && $requirements === '' && $responsibilities === '') {
    $description = trim($text);
}

echo json_encode([
    'ok' => true,
    'llm' => $llm_used,
    'description' => $description,
    'requirements' => $requirements,
    'responsibilities' => $responsibilities,
]);
