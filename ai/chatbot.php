<?php
/**
 * NovaHire AI - Career Assistant Chatbot
 * Intent-based rule engine (works offline) with optional LLM fallback for
 * open-ended questions. The bot has context about the logged-in user:
 * their skills, applications, saved jobs and quiz status.
 */

if (defined('AI_CHATBOT_LOADED')) return;
define('AI_CHATBOT_LOADED', true);

require_once __DIR__ . '/engine.php';

/**
 * Handle an incoming chat message.
 *
 * @param array  $user     user_info row
 * @param string $message
 * @param array  $context  optional extra context (applications count, saved jobs, quiz status, top match)
 * @return array{reply:string, intent:string, buttons:array, suggestion:array}
 */
function ai_chatbot_respond($user, $message, $context = array()) {
    $msg = strtolower(trim($message));

    // Quick intents - ordered by specificity
    $intents = array(
        'greeting'  => array(
            array('hi', 'hello', 'hey', 'salam', 'good morning', 'good evening', 'good afternoon'),
            function ($u, $c) {
                $name = isset($u['username']) ? $u['username'] : 'there';
                return array(
                    'reply'   => 'Hello ' . $name . '! I am your NovaHire AI career assistant. I can help you find jobs, prepare for assessments, improve your resume, and more. What would you like help with today?',
                    'buttons' => array('Find jobs for me', 'Improve my resume', 'Practice interview', 'How to apply?'),
                );
            },
        ),
        'find_jobs' => array(
            array('find job', 'job for me', 'recommend job', 'show job', 'job matching', 'best job', 'suggest job', 'suggestions', 'recommendations'),
            function ($u, $c) {
                $top = isset($c['top_match']) ? $c['top_match'] : null;
                $skills = isset($u['user_skills']) ? ai_skills_to_array($u['user_skills']) : array();
                $reply = 'I can recommend jobs matched to your profile';
                if (!empty($skills)) $reply .= ' (skills: ' . implode(', ', array_slice($skills, 0, 5)) . ')';
                $reply .= '.';
                if ($top) $reply .= ' Your top match right now is <strong>' . htmlspecialchars($top['job_title']) . '</strong> at ' . htmlspecialchars($top['company_name']) . ' with a ' . $top['ai']['score'] . '% match score.';
                $reply .= ' Head to <strong>Browse Jobs</strong> and look for the AI match percentage badges.';
                return array('reply' => $reply, 'buttons' => array('Browse Jobs', 'Resume Analyzer', 'Grooming Coach'));
            },
        ),
        'resume_improve' => array(
            array('resume', 'cv', 'improve my profile', 'improve my resume', 'profile score', 'cover letter', 'analy'),
            function ($u, $c) {
                $score = isset($c['resume_score']) ? intval($c['resume_score']) : null;
                $reply = 'Your resume is analysed across skills, education, experience, completeness and activity.';
                if ($score !== null) $reply .= ' Your current resume score is <strong>' . $score . '/100</strong>.';
                $reply .= ' Use the AI Resume Analyzer to see your strengths, gaps and 3-4 concrete improvements.';
                return array('reply' => $reply, 'buttons' => array('Analyze My Resume', 'Generate Cover Letter', 'Find jobs for me'));
            },
        ),
        'interview_practice' => array(
            array('interview', 'mock', 'practice', 'quiz practice', 'assessment'),
            function ($u, $c) {
                return array(
                    'reply'   => 'I can help you practice. The AI Mock Interview gives you real questions per category, scores your answers, and gives feedback. The grooming videos also cover exactly what the assessments test.',
                    'buttons' => array('Start Mock Interview', 'Grooming Coach', 'Find jobs for me'),
                );
            },
        ),
        'grooming' => array(
            array('grooming', 'learning', 'study', 'learn', 'video', 'training', 'improve skill'),
            function ($u, $c) {
                $reply = 'The Grooming Hub has curated video lessons per category (PHP, Java, Python, Frontend and more).';
                if (isset($c['grooming_progress']) && $c['grooming_progress']['total'] > 0) {
                    $p = $c['grooming_progress'];
                    $reply .= ' You have completed ' . $p['done'] . '/' . $p['total'] . ' videos in ' . htmlspecialchars($p['category']) . '.';
                }
                $reply .= ' The AI Grooming Coach builds a personalised study plan around your weak topics.';
                return array('reply' => $reply, 'buttons' => array('Grooming Coach', 'Start Mock Interview', 'Improve my resume'));
            },
        ),
        'how_apply' => array(
            array('how to apply', 'apply job', 'application process', 'how do i apply', 'submit application', 'apply'),
            function ($u, $c) {
                return array(
                    'reply'   => 'Applying is easy: 1) Browse or find a recommended job, 2) Open the job details to see the AI match score, 3) Take the assessment quiz if required (pass it to unlock applying), 4) Submit your application with a cover letter - you can generate one with AI.',
                    'buttons' => array('Browse Jobs', 'Generate Cover Letter', 'My Applications'),
                );
            },
        ),
        'status_check' => array(
            array('application status', 'status', 'my application', 'applied', 'shortlist', 'track'),
            function ($u, $c) {
                $apps = isset($c['applications_count']) ? intval($c['applications_count']) : 0;
                if ($apps > 0) {
                    return array('reply' => 'You have ' . $apps . ' application(s). Open the Applications page to see their status (Pending, Reviewed, Shortlisted, Rejected) and any scheduled interviews.', 'buttons' => array('My Applications', 'Find jobs for me'));
                }
                return array('reply' => 'You have not applied to any jobs yet. Let me help you find your first match!', 'buttons' => array('Find jobs for me', 'Browse Jobs'));
            },
        ),
        'thank' => array(
            array('thank', 'thanks', 'thx', 'appreciate', 'great help'),
            function ($u, $c) {
                return array('reply' => 'You are most welcome! Good luck with your job search. Remember you can ask me anytime for help with jobs, grooming, interviews or your resume.', 'buttons' => array('Find jobs for me', 'Analyze My Resume'));
            },
        ),
        'help' => array(
            array('help', 'what can you do', 'features', 'menu', 'options', 'capabil'),
            function ($u, $c) {
                return array(
                    'reply'   => 'Here is what I can do for you:',
                    'buttons' => array('Find jobs for me', 'Analyze My Resume', 'Start Mock Interview', 'Grooming Coach', 'Generate Cover Letter'),
                );
            },
        ),
    );

    foreach ($intents as $intent => $cfg) {
        foreach ($cfg[0] as $keyword) {
            if (strpos($msg, $keyword) !== false) {
                $result = $cfg[1]($user, $context);
                $result['intent'] = $intent;
                return $result;
            }
        }
    }

    // ---- LLM fallback for open-ended questions ----
    if (ai_llm_available()) {
        $profile = 'The user is a job seeker named ' . (isset($u['username']) ? $u['username'] : 'unknown') .
            ' with skills: ' . (isset($u['user_skills']) ? $u['user_skills'] : 'none') . '.';
        $system = 'You are the friendly support assistant of NovaHire, a job portal with AI job matching, grooming video training, quizzes, and mock interviews. Keep answers short (max 90 words) and helpful. Never invent personal data about the user. ' . $profile;
        $res = ai_llm_chat($system, $message, 250);
        if ($res['ok']) {
            return array('reply' => $res['text'], 'intent' => 'llm', 'buttons' => array(), 'suggestion' => array());
        }
    }

    // ---- Fallback responses ----
    $fallbacks = array(
        "I am not sure I understood that. You can ask me things like 'find jobs for me', 'improve my resume', 'start mock interview', or 'grooming coach'.",
        "Hmm, that is outside my core skills. Try asking about jobs, assessments, grooming, resume or interviews.",
    );
    return array(
        'reply'   => $fallbacks[array_rand($fallbacks)],
        'intent'  => 'fallback',
        'buttons' => array('Find jobs for me', 'What can you do?', 'Improve my resume'),
        'suggestion' => array(),
    );
}
