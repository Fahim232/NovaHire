<?php
/**
 * NovaHire AI - Cover Letter Generator
 * Produces a personalised cover letter from a user profile + job.
 * Uses the LLM when configured, otherwise falls back to a smart
 * template that inserts the candidate's real data.
 */

if (defined('AI_COVER_LETTER_LOADED')) return;
define('AI_COVER_LETTER_LOADED', true);

require_once __DIR__ . '/engine.php';

function ai_generate_cover_letter($user, $job) {
    $username   = isset($user['username']) ? $user['username'] : 'Candidate';
    $skills     = isset($user['user_skills']) ? ai_skills_to_array($user['user_skills']) : array();
    $degree     = trim((string)(isset($user['user_degree']) ? $user['user_degree'] : ''));
    $exp        = trim((string)(isset($user['experience']) ? $user['experience'] : ''));
    $about      = trim((string)(isset($user['about_me']) ? $user['about_me'] : ''));

    $job_title      = isset($job['job_title']) ? $job['job_title'] : 'the position';
    $company_name   = isset($job['company_name']) ? $job['company_name'] : 'your company';
    $job_cat        = isset($job['job_category']) ? $job['job_category'] : '';
    $job_skills     = isset($job['skills_required']) ? ai_skills_to_array($job['skills_required']) : array();

    $matched = array();
    $job_text = strtolower($job_title . ' ' . $job_cat . ' ' . implode(' ', $job_skills));
    foreach ($skills as $s) {
        if (ai_skill_matches($s, $job_text)) $matched[] = $s;
    }
    if (empty($matched)) $matched = array_slice($skills, 0, 4);

    $matched_str = implode(', ', array_slice($matched, 0, 6));
    if ($matched_str === '') $matched_str = 'my skills and experience';

    $exp_str = $exp !== '' ? $exp : 'my professional background';
    $about_str = $about !== '' ? ' ' . $about : '';

    // Optional LLM generation
    if (ai_llm_available()) {
        $system = 'You are an expert career coach who writes professional, concise cover letters. '
                . 'Use the provided facts ONLY - never invent degrees, companies, or experiences. '
                . 'Output plain text with a short opening, 2-3 body paragraphs and a closing.';
        $prompt = 'Job title: ' . $job_title . "\n"
                . 'Company: ' . $company_name . "\n"
                . 'Candidate name: ' . $username . "\n"
                . 'Skills: ' . implode(', ', $skills) . "\n"
                . 'Degree: ' . $degree . "\n"
                . 'Experience: ' . $exp . "\n"
                . 'About: ' . $about . "\n"
                . 'Write a professional cover letter (max 220 words).';
        $res = ai_llm_chat($system, $prompt, 500);
        if ($res['ok'] && trim($res['text']) !== '') {
            return array(
                'mode'       => 'llm',
                'title'      => 'Cover Letter for ' . $job_title,
                'content'    => trim($res['text']),
                'matched'    => $matched,
            );
        }
    }

    // Template fallback
    $date = date('F j, Y');
    $content = "Dear Hiring Manager,\n\n"
        . "I am writing to apply for the position of " . $job_title . " at " . $company_name . ". "
        . "With a strong background in " . ($job_cat !== '' ? strtolower($job_cat) . ' development and ' : '') . "my proficiency in "
        . $matched_str . ", I am confident I can contribute meaningfully to your team.\n\n"
        . "My experience - " . $exp_str . " - has prepared me to handle the responsibilities of this role. "
        . "Throughout my career I have focused on delivering high-quality work, collaborating with cross-functional teams, "
        . "and continuously improving my craft." . $about_str . "\n\n"
        . "I have verified my skills against the requirements for this role using the NovaHire AI matching engine, "
        . "and I am eager to bring my expertise to " . $company_name . ". I would welcome the opportunity to discuss "
        . "how I can add value to your organization.\n\n"
        . "Thank you for considering my application. I look forward to hearing from you.\n\n"
        . "Sincerely,\n" . $username;

    return array(
        'mode'    => 'template',
        'title'   => 'Cover Letter for ' . $job_title,
        'content' => $content,
        'matched' => $matched,
    );
}
