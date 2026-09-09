<?php
/**
 * NovaHire AI - Cover Letter Generator v2
 *
 * Produces a personalised cover letter from a user profile + job.
 * - When an LLM key is configured → uses OpenAI / Gemini.
 * - Otherwise → smart template engine with multiple tones, dynamic
 *   intros, matched-skill highlights, and a calculated match score.
 */

if (defined('AI_COVER_LETTER_LOADED')) return;
define('AI_COVER_LETTER_LOADED', true);

require_once __DIR__ . '/engine.php';

/* ════════════════════════════════════════════════════════════════
   PUBLIC API
   ════════════════════════════════════════════════════════════════ */

/**
 * Generate a cover letter.
 *
 * @param array  $user  Row from user_info.
 * @param array  $job   Row from company_jobs + companies JOIN.
 * @param string $tone  One of: professional, friendly, formal, confident
 * @return array{mode:string, title:string, content:string, matched:array, score:int, greeting:string, sign_off:string}
 */
function ai_generate_cover_letter($user, $job, $tone = 'professional') {
    $username   = $user['username']        ?? 'Candidate';
    $skills     = ai_skills_to_array($user['user_skills'] ?? '');
    $degree     = trim($user['user_degree'] ?? '');
    $exp        = trim($user['experience']  ?? '');
    $about      = trim($user['about_me']    ?? '');

    $job_title    = $job['job_title']        ?? 'the position';
    $company_name = $job['company_name']     ?? 'your company';
    $job_cat      = $job['job_category']     ?? '';
    $job_skills   = ai_skills_to_array($job['skills_required'] ?? '');

    // Match skills
    $matched   = _match_skills($skills, $job_title, $job_cat, $job_skills);
    $score     = _calc_match_score($skills, $job_skills, $exp, $degree, $about);
    $greeting  = _pick_greeting($tone);
    $sign_off  = _pick_sign_off($tone, $username);

    // LLM path
    if (ai_llm_available()) {
        $res = _llm_generate($username, $skills, $degree, $exp, $about,
                             $job_title, $company_name, $job_cat, $matched, $tone);
        if ($res['ok'] && trim($res['text']) !== '') {
            return [
                'mode'      => 'llm',
                'title'     => 'Cover Letter for ' . $job_title,
                'content'   => trim($res['text']),
                'matched'   => $matched,
                'score'     => $score,
                'greeting'  => $greeting,
                'sign_off'  => $sign_off,
            ];
        }
    }

    // Template path
    $content = _template_generate($username, $degree, $exp, $about,
                                  $job_title, $company_name, $job_cat,
                                  $matched, $score, $tone);

    return [
        'mode'      => 'template',
        'title'     => 'Cover Letter for ' . $job_title,
        'content'   => $content,
        'matched'   => $matched,
        'score'     => $score,
        'greeting'  => $greeting,
        'sign_off'  => $sign_off,
    ];
}

/* ════════════════════════════════════════════════════════════════
   SKILL MATCHING + SCORE
   ════════════════════════════════════════════════════════════════ */

function _match_skills($user_skills, $job_title, $job_cat, $job_skills) {
    $matched = [];
    $job_text = strtolower($job_title . ' ' . $job_cat . ' ' . implode(' ', $job_skills));
    foreach ($user_skills as $s) {
        if (ai_skill_matches($s, $job_text)) $matched[] = $s;
    }
    if (empty($matched) && !empty($user_skills)) {
        $matched = array_slice($user_skills, 0, 4);
    }
    return array_slice($matched, 0, 8);
}

function _calc_match_score($user_skills, $job_skills, $exp, $degree, $about) {
    $score = 0;

    // Skill overlap (0-50 pts)
    if (!empty($job_skills)) {
        $overlap = 0;
        foreach ($job_skills as $js) {
            foreach ($user_skills as $us) {
                if (ai_skill_matches($us, $js)) { $overlap++; break; }
            }
        }
        $score += min(50, round(($overlap / count($job_skills)) * 50));
    } else {
        $score += min(30, count($user_skills) * 5);
    }

    // Experience (0-20 pts)
    $years = ai_parse_years($exp);
    if ($years !== null) {
        if ($years >= 5)      $score += 20;
        elseif ($years >= 3)  $score += 15;
        elseif ($years >= 1)  $score += 10;
        else                  $score += 5;
    } else {
        $score += 5;
    }

    // Education (0-15 pts)
    if ($degree !== '') {
        $dl = strtolower($degree);
        if (strpos($dl, 'master') !== false || strpos($dl, 'mba') !== false || strpos($dl, 'm.sc') !== false) $score += 15;
        elseif (strpos($dl, 'bachelor') !== false || strpos($dl, 'b.sc') !== false || strpos($dl, 'b.tech') !== false || strpos($dl, 'b.eng') !== false) $score += 12;
        else $score += 8;
    }

    // About section (0-15 pts)
    if (mb_strlen($about) > 50) $score += 15;
    elseif (mb_strlen($about) > 20) $score += 8;
    else $score += 3;

    return min(100, $score);
}

/* ════════════════════════════════════════════════════════════════
   TONE HELPERS
   ════════════════════════════════════════════════════════════════ */

function _pick_greeting($tone) {
    $map = [
        'professional' => 'Dear Hiring Manager,',
        'formal'       => 'Dear Sir or Madam,',
        'friendly'     => 'Hello there,',
        'confident'    => 'Dear Hiring Team,',
    ];
    return $map[$tone] ?? $map['professional'];
}

function _pick_sign_off($tone, $name) {
    $map = [
        'professional' => "Sincerely,\n" . $name,
        'formal'       => "Respectfully,\n" . $name,
        'friendly'     => "Warm regards,\n" . $name,
        'confident'    => "Best regards,\n" . $name,
    ];
    return $map[$tone] ?? $map['professional'];
}

/* ════════════════════════════════════════════════════════════════
   SMART TEMPLATE ENGINE
   ════════════════════════════════════════════════════════════════ */

function _template_generate($name, $degree, $exp, $about,
                            $job_title, $company, $job_cat,
                            $matched, $score, $tone) {

    $date      = date('F j, Y');
    $greeting  = _pick_greeting($tone);
    $sign_off  = _pick_sign_off($tone, $name);
    $matched_s = implode(', ', array_slice($matched, 0, 6));
    if ($matched_s === '') $matched_s = 'my technical and professional skills';

    $years  = ai_parse_years($exp);
    $years_s = $years !== null
        ? ($years > 0 ? $years . ' year' . ($years > 1 ? 's' : '') . ' of professional experience' : 'a strong academic foundation and eagerness to grow')
        : 'a solid professional background';

    $degree_s = $degree !== '' ? $degree : 'my academic training';

    // Opening lines by tone
    $openings = [
        'professional' => [
            "I am writing to express my sincere interest in the {$job_title} position at {$company}.",
            "I am excited to apply for the {$job_title} role at {$company}, where I believe my background aligns well with your requirements.",
            "With my background in " . ($job_cat !== '' ? strtolower($job_cat) : 'this field') . " and proficiency in {$matched_s}, I am confident I can make a meaningful contribution to your team at {$company}.",
        ],
        'formal' => [
            "I wish to apply for the position of {$job_title} at {$company}.",
            "Please accept this letter as expression of my interest in the {$job_title} vacancy at {$company}.",
            "Having reviewed the requirements for this position, I believe my qualifications in " . ($job_cat !== '' ? strtolower($job_cat) : 'the relevant domain') . " and my expertise in {$matched_s} make me a suitable candidate.",
        ],
        'friendly' => [
            "I came across the {$job_title} opening at {$company} and got genuinely excited -- it feels like a perfect match for what I love doing.",
            "When I saw the {$job_title} role at {$company}, I knew I had to reach out. My experience with {$matched_s} lines up closely with what you're looking for.",
            "Hi! I'm thrilled to apply for the {$job_title} position at {$company}. I've been working with {$matched_s} and I'd love to bring that energy to your team.",
        ],
        'confident' => [
            "I'm writing to put my name forward for the {$job_title} position at {$company} -- and I'm confident I'm the right person for this role.",
            "The {$job_title} role at {$company} is exactly the kind of challenge I thrive on. My track record with {$matched_s} speaks for itself.",
            "I'm confident that my expertise in {$matched_s} and {$years_s} position me as a strong candidate for the {$job_title} role at {$company}.",
        ],
    ];

    $body_openings = [
        'professional' => [
            "My background includes {$years_s}, during which I have developed strong competencies in {$matched_s}. " . ($degree !== '' ? "I hold a {$degree}, which has provided me with a solid theoretical and practical foundation." : "My hands-on experience has equipped me with both the technical depth and problem-solving mindset needed for this role."),
            "Throughout my career, I have consistently demonstrated the ability to deliver quality results, collaborate effectively with cross-functional teams, and adapt quickly to new technologies and methodologies.",
        ],
        'formal' => [
            "My professional trajectory includes {$years_s}, with particular emphasis on {$matched_s}. " . ($degree !== '' ? "I hold a {$degree}, which has equipped me with the necessary theoretical knowledge and practical skills." : "I have consistently sought to develop my expertise through continuous learning and hands-on project experience."),
            "I am committed to maintaining the highest standards of professional conduct and delivering outcomes that exceed expectations in every assignment I undertake.",
        ],
        'friendly' => [
            "Over the past " . ($years !== null ? ($years > 0 ? $years . ' years' : 'few years of focused learning') : 'several years') . ", I've built real skills in {$matched_s}. " . ($degree !== '' ? "I also have a {$degree}, which gave me a great mix of theory and hands-on practice." : "I've learned by doing -- jumping into projects, picking up new tools, and always staying curious."),
            "I genuinely enjoy solving problems and working with people who care about what they build. Some highlights from my journey include working on cross-functional teams, adapting to fast-paced environments, and always looking for ways to improve.",
        ],
        'confident' => [
            "My track record includes {$years_s}, with proven results in {$matched_s}. " . ($degree !== '' ? "Combined with my {$degree}, I bring both the analytical rigor and practical capability needed for this role." : "I've consistently delivered results that move the needle, and I'm ready to do the same at {$company}."),
            "I don't just meet requirements -- I look for ways to add value beyond the job description. Whether it's streamlining processes, mentoring teammates, or driving innovation, I bring energy and ownership to everything I do.",
        ],
    ];

    $closings = [
        'professional' => "I would welcome the opportunity to discuss how my skills and experience can contribute to {$company}'s continued success. I am available at your convenience for an interview and look forward to the possibility of joining your team.",
        'formal'       => "I respectfully request the opportunity to discuss my qualifications in further detail. I am available for an interview at your earliest convenience and trust that my application will receive due consideration.",
        'friendly'     => "I'd love to chat more about how I can contribute to the team at {$company}. I'm flexible with scheduling and happy to connect whenever works best for you!",
        'confident'    => "I'm ready to hit the ground running and make an immediate impact at {$company}. Let's schedule a conversation to discuss how I can deliver real results for your team.",
    ];

    $about_line = '';
    if ($about !== '') {
        $about_line = ' ' . rtrim($about, '.') . '.';
    }

    $letter = $greeting . "\n\n"
        . implode("\n\n", $openings[$tone]) . "\n\n"
        . implode("\n\n", $body_openings[$tone]) . $about_line . "\n\n"
        . $closings[$tone] . "\n\n"
        . $sign_off . "\n"
        . $date;

    return $letter;
}

/* ════════════════════════════════════════════════════════════════
   LLM PATH
   ════════════════════════════════════════════════════════════════ */

function _llm_generate($name, $skills, $degree, $exp, $about,
                       $job_title, $company, $job_cat, $matched, $tone) {
    $system = 'You are an expert career coach who writes professional, concise, and personalised cover letters. '
            . 'Use the provided facts ONLY -- never invent degrees, companies, or experiences. '
            . 'Match the tone: ' . $tone . '. '
            . 'Output plain text (no markdown, no bullet lists). '
            . 'Structure: greeting, 2-3 body paragraphs, closing, sign-off. Max 250 words.';

    $prompt = "Job title: {$job_title}\n"
            . "Company: {$company}\n"
            . "Category: {$job_cat}\n"
            . "Candidate name: {$name}\n"
            . "Skills: " . implode(', ', $skills) . "\n"
            . "Matched skills: {$matched}\n"
            . "Degree: {$degree}\n"
            . "Experience: {$exp}\n"
            . "About: {$about}\n"
            . "Tone: {$tone}\n"
            . "Write a cover letter.";

    return ai_llm_chat($system, $prompt, 600);
}
