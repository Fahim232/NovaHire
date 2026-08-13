<?php
/**
 * NovaHire AI — Job Matching Engine
 *
 * Computes a 0–100 match score between a candidate's profile and a job,
 * plus matched/missing skills and plain-English recommendations.
 *
 * Weighting:
 *   Skills overlap   60%
 *   Category fit     15%
 *   Experience       15%
 *   Education/degree 10%
 *
 * Works 100% offline (rule-based). Optional LLM adds a written summary.
 */

if (defined('AI_MATCHING_LOADED')) return;
define('AI_MATCHING_LOADED', true);

require_once __DIR__ . '/engine.php';

/**
 * @param array  $user  user_info row (user_skills, user_degree, experience, about_me)
 * @param array  $job   company_jobs row (job_category, skills_required, requirements,
 *                      experience_required, job_title, job_description, responsibilities)
 * @return array{score:int, matched_skills:array, missing_skills:array, experience:array,
 *               category:array, education:array, breakdown:array, label:string, label_color:string,
 *               suggestions:array, llm_summary:string}
 */
function ai_match_profile_job($user, $job) {
    $user_skills = isset($user['user_skills']) ? ai_skills_to_array($user['user_skills']) : [];
    $job_skills  = isset($job['skills_required']) ? ai_skills_to_array($job['skills_required']) : [];

    // Also mine requirements/responsibilities/description for extra skill terms
    $job_text = strtolower(
        ($job['job_category'] ?? '') . ' ' .
        ($job['job_title'] ?? '') . ' ' .
        ($job['skills_required'] ?? '') . ' ' .
        ($job['requirements'] ?? '') . ' ' .
        ($job['responsibilities'] ?? '') . ' ' .
        ($job['job_description'] ?? '')
    );

    $matched = [];
    $missing = [];
    foreach ($job_skills as $js) {
        $hit = false;
        foreach ($user_skills as $us) {
            if (ai_skill_matches($us, $js) || ai_skill_matches($js, $us)) {
                $hit = true;
                if (!in_array($us, $matched)) $matched[] = $us;
                break;
            }
        }
        if (!$hit && $js !== '' && !in_array($js, $missing)) {
            // only report meaningful missing skills
            if (strlen($js) > 1) $missing[] = $js;
        }
    }
    // extra matched skills from user that appear anywhere in the job
    foreach ($user_skills as $us) {
        if (ai_skill_matches($us, $job_text) && !in_array($us, $matched)) {
            $matched[] = $us;
        }
    }

    $job_skill_count = count($job_skills);
    $skill_score = 0;
    if ($job_skill_count > 0) {
        $skill_score = round((count($matched) / $job_skill_count) * 100);
    } elseif (!empty($user_skills) && !empty($matched)) {
        $skill_score = 60;
    }

    // ── Category fit ──
    $cat_score = 0;
    $cat_note = 'Category not clearly related to your profile.';
    $user_cat = ai_detect_category(implode(' ', array_merge($user_skills, [$user['user_degree'] ?? '', $user['experience'] ?? ''])));
    $job_cat = ai_norm($job['job_category'] ?? '');
    if ($job_cat !== '') {
        $cat_aliases = [
            'frontend' => ['frontend', 'front-end', 'javascript', 'react'],
            'javascript' => ['frontend', 'front-end', 'javascript', 'react'],
            'php' => ['php', 'laravel'],
            'datascience' => ['data science', 'machine learning', 'python'],
        ];
        $match = false;
        if (ai_skill_matches($job_cat, $user_cat) || ai_skill_matches($user_cat, $job_cat)) {
            $match = true;
        }
        if (isset($cat_aliases[$job_cat]) && in_array($user_cat, array_map('ai_norm', $cat_aliases[$job_cat]))) {
            $match = true;
        }
        if ($match) {
            $cat_score = 100;
            $cat_note = "Your profile aligns with the $job_cat category.";
        } elseif ($user_cat !== null && $user_cat !== '') {
            $cat_score = 30;
            $cat_note = "Job is in the $job_cat category but your profile is strongest in $user_cat.";
        } else {
            $cat_score = 40;
            $cat_note = "Job is in the $job_cat category — make sure to highlight related experience.";
        }
    }

    // ── Experience ──
    $exp_score = 50;
    $exp_note = 'Experience could not be precisely compared.';
    $exp_required = ai_parse_years($job['experience_required'] ?? '');
    $exp_user = ai_parse_years($user['experience'] ?? '');
    if ($exp_required !== null) {
        if ($exp_user === null) {
            $exp_score = 50;
            $exp_note = "Job requires $exp_required+ years; update your profile's experience to compare.";
        } elseif ($exp_user >= $exp_required) {
            $exp_score = 100;
            $exp_note = "You have $exp_user+ years of experience, meeting the $exp_required+ years requirement.";
        } elseif ($exp_user >= max(1, $exp_required - 1)) {
            $exp_score = 70;
            $exp_note = "You have $exp_user+ years; the role asks for $exp_required+ years — close enough to apply.";
        } else {
            $exp_score = 35;
            $exp_note = "The role requires $exp_required+ years but you list $exp_user+ years.";
        }
    }

    // ── Education ──
    $edu_score = 60;
    $edu_note = 'Education requirement not specified; your degree is a plus.';
    $edu_text = strtolower(trim($user['user_degree'] ?? ''));
    $req_text = strtolower(trim($job['requirements'] ?? ''));
    if ($edu_text === '') {
        $edu_score = 40;
        $edu_note = 'Add your degree to your profile for a better education match.';
    } elseif (strpos($req_text, 'bachelor') !== false || strpos($req_text, 'degree') !== false) {
        if (strpos($edu_text, 'bachelor') !== false || strpos($edu_text, 'bsc') !== false || strpos($edu_text, 'bs ') !== false || strpos($edu_text, 'computer science') !== false) {
            $edu_score = 100;
            $edu_note = 'Your degree satisfies the education requirement.';
        } else {
            $edu_score = 60;
            $edu_note = 'A degree is preferred; yours is listed and may qualify.';
        }
    } elseif (strpos($req_text, 'master') !== false) {
        if (strpos($edu_text, 'master') !== false || strpos($edu_text, 'msc') !== false) {
            $edu_score = 100;
            $edu_note = 'Your postgraduate degree matches the requirement.';
        } else {
            $edu_score = 50;
            $edu_note = 'A master\'s degree is preferred for this role.';
        }
    } elseif ($edu_text !== '') {
        $edu_score = 85;
        $edu_note = 'No strict degree requirement; your qualifications are sufficient.';
    }

    // ── Final weighted score ──
    $score = (int)round(
        $skill_score * 0.60 +
        $cat_score   * 0.15 +
        $exp_score   * 0.15 +
        $edu_score   * 0.10
    );
    $score = max(0, min(100, $score));

    $label = ai_readiness_label($score);

    // ── Suggestions ──
    $suggestions = [];
    if (!empty($missing)) {
        $suggestions[] = 'Missing skills to add: <strong>' . implode(', ', array_slice($missing, 0, 6)) . '</strong>.' . (count($missing) > 6 ? ' And more.' : '');
    }
    if ($exp_required !== null && $exp_user !== null && $exp_user < $exp_required) {
        $suggestions[] = "Consider gaining around " . ($exp_required - $exp_user) . " more year(s) of relevant experience, or highlight transferable experience in your cover letter.";
    }
    if (isset($missing[0]) && in_array(strtolower($missing[0]), array_map('ai_norm', array_merge(['leadership', 'communication', 'teamwork'])))) {
        $suggestions[] = 'Soft skills like ' . $missing[0] . ' can be emphasised through project examples and your grooming training.';
    }
    if (empty($suggestions)) {
        $suggestions[] = 'Your profile is a strong fit — highlight your matched skills in the cover letter and application.';
    }

    // ── Optional LLM summary ──
    $llm_summary = '';
    if (ai_llm_available()) {
        $system = 'You are a concise career coach for a job portal. Reply in 2–3 short sentences maximum, plain text.';
        $prompt = "Candidate skills: " . implode(', ', $user_skills) . "\n"
                . "Candidate degree: {$user['user_degree']}\n"
                . "Job title: {$job['job_title']}\n"
                . "Job category: {$job['job_category']}\n"
                . "Match score: {$score}%\n"
                . "Matched skills: " . implode(', ', $matched) . "\n"
                . "Missing skills: " . implode(', ', $missing) . "\n"
                . "Give the candidate one actionable tip to improve their chance.";
        $res = ai_llm_chat($system, $prompt, 200);
        if ($res['ok']) $llm_summary = $res['text'];
    }

    return [
        'score'        => $score,
        'matched_skills' => $matched,
        'missing_skills' => $missing,
        'experience'   => ['score' => $exp_score, 'note' => $exp_note, 'required' => $exp_required, 'user' => $exp_user],
        'category'     => ['score' => $cat_score, 'note' => $cat_note],
        'education'    => ['score' => $edu_score, 'note' => $edu_note],
        'breakdown'    => ['skills' => $skill_score, 'category' => $cat_score, 'experience' => $exp_score, 'education' => $edu_score],
        'label'        => $label[0],
        'label_color'  => $label[1],
        'suggestions'  => $suggestions,
        'llm_summary'  => $llm_summary,
    ];
}

/**
 * Rank a list of jobs against a user profile.
 * @param array $user
 * @param array $jobs list of company_jobs rows
 * @return array sorted list with 'ai' match data attached
 */
function ai_rank_jobs($user, $jobs) {
    $out = [];
    foreach ($jobs as $job) {
        $ai = ai_match_profile_job($user, $job);
        $job['ai'] = $ai;
        $out[] = $job;
    }
    usort($out, function ($a, $b) {
        return $b['ai']['score'] <=> $a['ai']['score'];
    });
    return $out;
}
