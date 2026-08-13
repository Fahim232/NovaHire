<?php
/**
 * NovaHire AI - Resume / CV Analyzer
 * Scores a candidate's profile across five dimensions and produces an
 * actionable improvement plan. Fully offline rule-based, with an optional
 * LLM-generated narrative summary.
 * Dimensions: Skills, Education, Experience, Profile Completeness, Career Focus
 */

if (defined('AI_RESUME_LOADED')) return;
define('AI_RESUME_LOADED', true);

require_once __DIR__ . '/engine.php';

function ai_analyze_resume($user, $job_counts = array()) {
    $dims = array();

    // ---- 1. Skills ----
    $skills = isset($user['user_skills']) ? ai_skills_to_array($user['user_skills']) : array();
    $skill_count = count($skills);
    if ($skill_count >= 8) {
        $skill_score = 100;
        $skill_note = 'Excellent - you have ' . $skill_count . ' skills listed, a strong signal for recruiters.';
    } elseif ($skill_count >= 5) {
        $skill_score = 80;
        $skill_note = 'Good skill coverage (' . $skill_count . ' skills). Consider adding tools and frameworks you use.';
    } elseif ($skill_count >= 3) {
        $skill_score = 55;
        $skill_note = 'Only ' . $skill_count . ' skills listed. Add all relevant technical and soft skills.';
    } elseif ($skill_count > 0) {
        $skill_score = 35;
        $skill_note = 'Your skills list is very short - recruiters filter heavily on this field.';
    } else {
        $skill_score = 15;
        $skill_note = 'No skills listed. Add your core skills to appear in recruiter searches.';
    }
    $dims['skills'] = array('score' => $skill_score, 'note' => $skill_note, 'count' => $skill_count);

    // ---- 2. Education ----
    $edu = trim((string)(isset($user['user_degree']) ? $user['user_degree'] : ''));
    if ($edu === '') {
        $dims['education'] = array('score' => 20, 'note' => 'No education listed. Add your degree or certifications.', 'count' => 0);
    } else {
        $edu_score = 70;
        $lower = strtolower($edu);
        if (strpos($lower, 'master') !== false || strpos($lower, 'mba') !== false || strpos($lower, 'msc') !== false || strpos($lower, 'phd') !== false) {
            $edu_score = 95;
        } elseif (strpos($lower, 'bachelor') !== false || strpos($lower, 'bsc') !== false || strpos($lower, 'computer') !== false || strpos($lower, 'b.') !== false) {
            $edu_score = 85;
        }
        $dims['education'] = array('score' => $edu_score, 'note' => 'Education recorded: ' . $edu, 'count' => 1);
    }

    // ---- 3. Experience ----
    $exp = trim((string)(isset($user['experience']) ? $user['experience'] : ''));
    $years = ai_parse_years($exp);
    if ($years === null) {
        $dims['experience'] = array('score' => 40, 'note' => 'Experience unclear. Write like "3+ years" or "5 years experience".', 'count' => 0);
    } elseif ($years >= 5) {
        $dims['experience'] = array('score' => 100, 'note' => 'Senior-level experience (' . $years . '+ years).', 'count' => $years);
    } elseif ($years >= 2) {
        $dims['experience'] = array('score' => 80, 'note' => 'Good experience level (' . $years . '+ years).', 'count' => $years);
    } elseif ($years >= 1) {
        $dims['experience'] = array('score' => 60, 'note' => 'Early-career experience (' . $years . ' year).', 'count' => $years);
    } else {
        $dims['experience'] = array('score' => 45, 'note' => 'Entry-level profile. Add internships and projects.', 'count' => $years);
    }

    // ---- 4. Profile Completeness ----
    $fields = array(
        'username' => 15, 'email' => 15, 'phone' => 15, 'user_degree' => 15,
        'user_skills' => 20, 'profile' => 10, 'about_me' => 10,
    );
    $completion = 0;
    foreach ($fields as $f => $w) {
        if (!empty($user[$f])) $completion += $w;
    }
    if ($completion >= 100) {
        $dims['completeness'] = array('score' => 100, 'note' => 'Profile is 100% complete. Great!', 'count' => 100);
    } elseif ($completion >= 70) {
        $dims['completeness'] = array('score' => 80, 'note' => 'Profile ' . $completion . '% complete - finish the remaining fields.', 'count' => $completion);
    } else {
        $dims['completeness'] = array('score' => max(20, $completion), 'note' => 'Profile only ' . $completion . '% complete - incomplete profiles get fewer views.', 'count' => $completion);
    }

    // ---- 5. Career Focus / Activity ----
    $focus_score = 70;
    $focus_note = 'Regular activity helps you appear in recruiter searches.';
    if (!empty($job_counts)) {
        $apps = isset($job_counts['applications']) ? $job_counts['applications'] : 0;
        $saved = isset($job_counts['saved']) ? $job_counts['saved'] : 0;
        $passed = isset($job_counts['quiz_passed']) ? $job_counts['quiz_passed'] : 0;
        if ($apps >= 10) {
            $focus_score = 95;
            $focus_note = 'Very active - ' . $apps . ' applications submitted. Keep quality high.';
        } elseif ($apps >= 4) {
            $focus_score = 80;
            $focus_note = 'Good activity (' . $apps . ' applications, ' . $saved . ' saved jobs).';
        } elseif ($apps >= 1) {
            $focus_score = 60;
            $focus_note = 'You have started applying (' . $apps . ' applications). Aim for consistent applications.';
        } else {
            $focus_score = 40;
            $focus_note = 'No applications yet. Use the AI job matching to find your first best-fit job.';
        }
        if ($passed > 0) {
            $focus_note .= ' You passed ' . $passed . ' assessment quiz(zes).';
            $focus_score = min(100, $focus_score + 5);
        }
    }
    $dims['career'] = array('score' => $focus_score, 'note' => $focus_note, 'count' => 0);

    // ---- Total (average) ----
    $total = 0;
    foreach ($dims as $d) { $total += $d['score']; }
    $total = (int)round($total / count($dims));
    $total = max(0, min(100, $total));

    // ---- Strengths & gaps ----
    $strengths = array();
    $gaps = array();
    foreach ($dims as $key => $d) {
        if ($d['score'] >= 80) {
            $strengths[] = ucfirst($key) . ': ' . $d['note'];
        } elseif ($d['score'] < 50) {
            $gaps[] = ucfirst($key) . ': ' . $d['note'];
        }
    }

    // ---- Suggestions ----
    $suggestions = array();
    if ($skill_count < 5) {
        $suggestions[] = 'List at least 5-8 skills, mixing hard skills (languages, frameworks) with soft skills (communication, teamwork).';
    }
    if ($edu === '') {
        $suggestions[] = 'Add your degree and any certifications - many jobs filter candidates by education.';
    }
    if ($years === null) {
        $suggestions[] = 'Rewrite your experience as "X years" so the matching engine can compare you with job requirements.';
    }
    if ($completion < 100) {
        $suggestions[] = 'Complete your profile: add a profile photo, about me, and full contact details to reach 100%.';
    }
    if (empty($suggestions)) {
        $suggestions[] = 'Your resume is in strong shape. Keep it updated as you learn new skills.';
    }

    $llm_summary = '';
    if (ai_llm_available()) {
        $system = 'You are a professional resume reviewer. Reply in 3 short sentences, plain text, no markdown.';
        $prompt = 'Skills: ' . implode(', ', $skills) . "\nDegree: {$edu}\nExperience: {$exp}\nOverall resume score: {$total}/100\nGive 3 quick, specific improvements.";
        $res = ai_llm_chat($system, $prompt, 220);
        if ($res['ok']) $llm_summary = $res['text'];
    }

    return array(
        'total'       => $total,
        'dimensions'  => $dims,
        'strengths'   => $strengths,
        'gaps'        => $gaps,
        'suggestions' => $suggestions,
        'llm_summary' => $llm_summary,
    );
}
