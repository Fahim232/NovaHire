<?php
/**
 * NovaHire AI - Career Assistant Chatbot v2
 *
 * Realistic, context-aware conversational engine.
 * - Remembers conversation history within the session
 * - Pulls real data from the database (jobs, applications, skills)
 * - Natural language intent matching with fuzzy tolerance
 * - Empathetic, personality-driven responses
 * - Always suggests actionable next steps
 */

if (defined('AI_CHATBOT_LOADED')) return;
define('AI_CHATBOT_LOADED', true);

require_once __DIR__ . '/engine.php';

/**
 * Precise word-boundary match: checks if any keyword phrase appears in the message.
 * Avoids false positives like "hi" matching inside "this".
 */
function ai_msg_contains($msg, $keywords) {
    foreach ($keywords as $kw) {
        // For short keywords (<=3 chars), require word boundaries
        if (strlen($kw) <= 3) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $msg)) return true;
        } else {
            if (strpos($msg, $kw) !== false) return true;
        }
    }
    return false;
}

/**
 * Main entry point - handle an incoming chat message.
 */
function ai_chatbot_respond($user, $message, $context = array()) {
    $msg = strtolower(trim($message));
    $name = isset($user['username']) ? explode(' ', $user['username'])[0] : 'there';
    $skills = isset($user['user_skills']) ? $user['user_skills'] : '';
    $skill_list = ai_skills_to_array($skills);

    // Conversation context tracking (session-based)
    if (!isset($_SESSION['ai_conv'])) {
        $_SESSION['ai_conv'] = ['topics' => [], 'last_intent' => '', 'msg_count' => 0, 'asked_about' => []];
    }
    $conv = &$_SESSION['ai_conv'];
    $conv['msg_count']++;

    // Detect sentiment hints
    $sentiment = ai_detect_sentiment($msg);

    // ── Intent matching (ordered by specificity) ──
    // Use ai_msg_contains() for precise word-boundary matching
    $intents = [

        // ─── GREETINGS ───
        'greeting' => [
            ['hello', 'hi there', 'hi!', 'hey', 'salam', 'good morning', 'good evening', 'good afternoon', 'howdy', 'sup?', 'yo!'],
            function ($u, $c, $conv) use ($name) {
                $greetings = [
                    "Hey $name! Great to see you. I've been looking at your profile and I have some ideas to help you land your next role. What would you like to work on?",
                    "Hi $name! Welcome back. I'm here to help with anything job-related - from finding the perfect match to prepping for interviews. What's on your mind?",
                    "Hello $name! Ready to take your career to the next level? I can help you find jobs, polish your resume, or practice interview questions. Just ask!",
                    "Hey there! I'm your NovaHire AI assistant. I know your skills and the current job market. Let's find you something amazing. What should we start with?",
                ];
                $conv['topics'][] = 'greeting';
                return [
                    'reply' => $greetings[array_rand($greetings)],
                    'buttons' => ai_suggest_buttons($u, $c, 'greeting'),
                ];
            },
        ],

        // ─── FRUSTRATION / NEGATIVITY (check before find_jobs to avoid false matches) ───
        'frustrated' => [
            ['frustrated', 'no job', 'cant find', 'can\'t find', 'cant get', 'rejected', 'no response', 'struggling', 'difficult', 'hard to', 'stuck', 'hopeless', 'give up', 'tired of', 'annoying', 'upset', 'disappointed', 'depressed', 'sad', 'unhappy', 'angry', 'hate'],
            function ($u, $c, $conv) {
                $reply = "I hear you, and it's completely normal to feel that way. Job hunting can be tough. ";
                $apps = isset($c['applications_count']) ? intval($c['applications_count']) : 0;
                if ($apps > 0) {
                    $reply .= "You've already applied to {$apps} job(s) - that shows persistence, which employers value. ";
                }
                $reply .= "Here's what I suggest: let's review your resume together to make sure it's as strong as possible, then target jobs that really match your skills. ";
                $reply .= "Small improvements in your approach can lead to big results. Want to start with a resume check?";

                $conv['topics'][] = 'frustrated';
                return [
                    'reply' => $reply,
                    'buttons' => ['Analyze My Resume', 'Find jobs for me', 'Start Mock Interview'],
                ];
            },
        ],

        // ─── FIND JOBS ───
        'find_jobs' => [
            ['find job', 'find me', 'job for me', 'recommend job', 'show job', 'job matching', 'best job', 'suggest job', 'suggestions', 'recommendations', 'looking for', 'need a job', 'search job', 'browse job', 'openings', 'vacancy', 'vacancies', 'hiring', 'positions', 'opportunities', 'job search', 'any job', 'new job', 'get a job'],
            function ($u, $c, $conv) use ($skill_list) {
                $top = isset($c['top_match']) ? $c['top_match'] : null;
                $active_jobs = isset($c['active_jobs_count']) ? intval($c['active_jobs_count']) : 0;
                $reply = '';

                if ($top) {
                    $match_pct = $top['ai']['score'];
                    $reply = "Based on your skills";
                    if (!empty($skill_list)) {
                        $reply .= " (" . implode(', ', array_slice($skill_list, 0, 3)) . ")";
                    }
                    $reply .= ", I found <strong>" . htmlspecialchars($top['job_title']) . "</strong> at <strong>" . htmlspecialchars($top['company_name']) . "</strong> as your top match with a <strong>{$match_pct}% match</strong>!";

                    if ($match_pct >= 80) {
                        $reply .= " This is an excellent fit - I'd strongly recommend applying.";
                    } elseif ($match_pct >= 60) {
                        $reply .= " This is a solid match. Worth checking out the full details.";
                    } else {
                        $reply .= " It covers some of your skills. You might want to browse more options to find a better fit.";
                    }
                } else {
                    $reply = "I'm scanning our job listings for you";
                    if (!empty($skill_list)) {
                        $reply .= " based on your " . implode(', ', array_slice($skill_list, 0, 3)) . " skills";
                    }
                    $reply .= ".";
                }

                if ($active_jobs > 0) {
                    $reply .= " There are currently <strong>{$active_jobs} active job listings</strong> on NovaHire right now.";
                }

                $reply .= " Check the Browse Jobs page - you'll see AI match percentage badges on each listing to help you prioritize.";

                $conv['topics'][] = 'find_jobs';
                $conv['last_intent'] = 'find_jobs';
                return [
                    'reply' => $reply,
                    'buttons' => ['Browse Jobs', 'Saved Jobs', 'My Applications', 'Resume Analyzer'],
                ];
            },
        ],

        // ─── RESUME / PROFILE ───
        'resume' => [
            ['resume', 'cv', 'improve my profile', 'improve my resume', 'profile score', 'profile', 'analy', 'weakness', 'strength', 'improve skill', 'skill gap'],
            function ($u, $c, $conv) {
                $score = isset($c['resume_score']) ? intval($c['resume_score']) : 0;
                $reply = '';

                if ($score >= 85) {
                    $reply = "Your resume is in excellent shape at <strong>{$score}/100</strong>! You've got a comprehensive profile. ";
                    $reply .= "To stand out even more, consider adding a professional summary or recent certifications.";
                } elseif ($score >= 60) {
                    $reply = "Your resume score is <strong>{$score}/100</strong> - decent but there's room to grow. ";
                    $missing = [];
                    if (empty($u['user_skills'])) $missing[] = 'skills';
                    if (empty($u['about_me'])) $missing[] = 'about me section';
                    if (empty($u['user_degree'])) $missing[] = 'education';
                    if (empty($u['phone'])) $missing[] = 'phone number';
                    if (!empty($missing)) {
                        $reply .= "I noticed you're missing: <strong>" . implode(', ', $missing) . "</strong>. ";
                    }
                    $reply .= "Use the Resume Analyzer for a detailed breakdown with specific improvement tips.";
                } else {
                    $reply = "Your profile score is <strong>{$score}/100</strong> - there's significant room for improvement. ";
                    $reply .= "Employers look at completeness first. Let me help you fix that!";
                }

                $conv['topics'][] = 'resume';
                $conv['last_intent'] = 'resume';
                return [
                    'reply' => $reply,
                    'buttons' => ['Analyze My Resume', 'Generate Cover Letter', 'Find jobs for me'],
                ];
            },
        ],

        // ─── INTERVIEW PREP ───
        'interview' => [
            ['interview', 'mock', 'practice', 'quiz practice', 'assessment', 'question', 'behavioral', 'technical question', 'prep'],
            function ($u, $c, $conv) {
                $passed = isset($c['quiz_passed']) ? intval($c['quiz_passed']) : 0;
                $reply = "The AI Mock Interview is your best prep tool. ";

                if ($passed > 0) {
                    $reply .= "You've already passed <strong>{$passed} assessment(s)</strong> - great progress! ";
                    $reply .= "Keep sharpening your skills with more practice rounds. Each session gives you personalized feedback.";
                } else {
                    $reply .= "It gives you real interview questions based on your target role, scores your answers, and provides detailed feedback on what to improve. ";
                    $reply .= "Many candidates who practice here significantly improve their interview performance.";
                }

                $reply .= " You can also browse the grooming videos for in-depth topic coverage.";

                $conv['topics'][] = 'interview';
                $conv['last_intent'] = 'interview';
                return [
                    'reply' => $reply,
                    'buttons' => ['Start Mock Interview', 'Grooming Coach', 'Find jobs for me'],
                ];
            },
        ],

        // ─── GROOMING / LEARNING ───
        'grooming' => [
            ['grooming', 'learning', 'study', 'learn', 'video', 'training', 'course', 'tutorial', 'improve skill', 'upskill', 'coaching', 'coach', 'weak topic', 'study plan'],
            function ($u, $c, $conv) use ($skill_list) {
                $reply = "The Grooming Hub has curated video lessons across multiple categories: PHP, Java, Python, Frontend, and more.";

                // Detect which category they might need
                $detected = null;
                if (!empty($skill_list)) {
                    foreach ($skill_list as $s) {
                        $cat = ai_detect_category($s);
                        if ($cat) { $detected = $cat; break; }
                    }
                }

                if ($detected) {
                    $reply .= " Based on your skills, I'd recommend starting with the <strong>{$detected}</strong> category.";
                }

                $reply .= " The AI Grooming Coach analyzes your weak areas and builds a personalized study plan. It's like having a personal tutor!";

                $conv['topics'][] = 'grooming';
                $conv['last_intent'] = 'grooming';
                return [
                    'reply' => $reply,
                    'buttons' => ['Grooming Coach', 'Start Mock Interview', 'Improve my resume'],
                ];
            },
        ],

        // ─── HOW TO APPLY ───
        'how_apply' => [
            ['how to apply', 'apply job', 'application process', 'how do i apply', 'submit application', 'apply', 'step by step', 'process'],
            function ($u, $c, $conv) {
                $reply = "Here's how to apply on NovaHire:<br><br>";
                $reply .= "<strong>1.</strong> Browse jobs or let me recommend matches based on your skills<br>";
                $reply .= "<strong>2.</strong> Open a job to see the AI match score - higher score = better fit<br>";
                $reply .= "<strong>3.</strong> If the job has an assessment quiz, take it first (you need to pass to apply)<br>";
                $reply .= "<strong>4.</strong> Write your cover letter - you can generate one with AI in seconds<br>";
                $reply .= "<strong>5.</strong> Submit! Track your application status anytime on the dashboard.<br><br>";
                $reply .= "Pro tip: Tailor your cover letter to each job. Our AI Cover Letter Generator does this automatically!";

                $conv['topics'][] = 'how_apply';
                return [
                    'reply' => $reply,
                    'buttons' => ['Browse Jobs', 'Generate Cover Letter', 'My Applications'],
                ];
            },
        ],

        // ─── APPLICATION STATUS ───
        'status' => [
            ['application status', 'status', 'my application', 'applied', 'shortlist', 'track', 'where did i apply', 'my applications', 'progress'],
            function ($u, $c, $conv) {
                $apps = isset($c['applications_count']) ? intval($c['applications_count']) : 0;
                $reply = '';

                if ($apps > 0) {
                    $reply = "You have <strong>{$apps} application(s)</strong> on file. ";
                    $reply .= "Head to the Applications page to see detailed statuses: Pending, Reviewed, Shortlisted, or Rejected. ";
                    $reply .= "You can also see any scheduled interviews and employer feedback there.";

                    if ($apps < 5) {
                        $reply .= " I'd suggest applying to more jobs to increase your chances. Want me to find more matches?";
                    }
                } else {
                    $reply = "You haven't applied to any jobs yet. That's okay - let's get you started! ";
                    $reply .= "I can recommend jobs that match your skills right now. The sooner you apply, the sooner you'll hear back.";
                }

                $conv['topics'][] = 'status';
                return [
                    'reply' => $reply,
                    'buttons' => $apps > 0 ? ['My Applications', 'Find jobs for me'] : ['Find jobs for me', 'Browse Jobs'],
                ];
            },
        ],

        // ─── SAVED JOBS ───
        'saved' => [
            ['saved', 'bookmark', 'saved job', 'my saved', 'shortlist job', 'wishlist'],
            function ($u, $c, $conv) {
                $saved = isset($c['saved_count']) ? intval($c['saved_count']) : 0;
                $reply = '';
                if ($saved > 0) {
                    $reply = "You have <strong>{$saved} saved job(s)</strong>. These are jobs you bookmarked for later. ";
                    $reply .= "Check them before they expire - deadlines sneak up fast! If you're ready, apply to your top picks.";
                } else {
                    $reply = "You haven't saved any jobs yet. When you browse jobs, click the bookmark icon to save interesting ones for later. ";
                    $reply .= "It's a great way to shortlist options before making a decision.";
                }
                $conv['topics'][] = 'saved';
                return [
                    'reply' => $reply,
                    'buttons' => ['Saved Jobs', 'Find jobs for me', 'Browse Jobs'],
                ];
            },
        ],

        // ─── COVER LETTER ───
        'cover_letter' => [
            ['cover letter', 'coverletter', 'cover', 'letter', 'write letter', 'draft letter'],
            function ($u, $c, $conv) {
                $reply = "The AI Cover Letter Generator creates a personalized, professional cover letter in seconds. ";
                $reply .= "It analyzes the job description, matches it with your skills and experience, and writes a tailored letter. ";
                $reply .= "You can edit the generated letter before submitting. Most recruiters say a good cover letter makes a real difference!";

                $conv['topics'][] = 'cover_letter';
                return [
                    'reply' => $reply,
                    'buttons' => ['Generate Cover Letter', 'Analyze My Resume', 'Find jobs for me'],
                ];
            },
        ],

        // ─── PAYMENT / SUBSCRIPTION ───
        'payment' => [
            ['payment', 'subscribe', 'subscription', 'premium', 'plan', 'pricing', 'pay', 'cost', 'free', 'upgrade'],
            function ($u, $c, $conv) {
                $reply = "NovaHire offers both free and premium features. ";
                $reply .= "You can browse jobs, take basic assessments, and use the AI assistant for free. ";
                $reply .= "Premium subscriptions unlock advanced grooming content, priority support, and enhanced AI features. ";
                $reply .= "Check the Pricing page for current plans and offers.";

                $conv['topics'][] = 'payment';
                return [
                    'reply' => $reply,
                    'buttons' => ['View Plans', 'Browse Jobs', 'What can you do?'],
                ];
            },
        ],

        // ─── THANK YOU ───
        'thank' => [
            ['thank', 'thanks', 'thx', 'appreciate', 'great help', 'awesome', 'perfect', 'excellent', 'good job'],
            function ($u, $c, $conv) {
                $replies = [
                    "You're welcome, $name! I'm always here when you need career guidance. Good luck with your job search!",
                    "Happy to help! Remember, I'm available 24/7 for any career questions. You've got this!",
                    "Anytime! I love seeing people take charge of their careers. Let me know if you need anything else.",
                    "Glad I could help! Keep pushing forward - the right opportunity is out there. I'll be here if you need me.",
                ];
                return [
                    'reply' => $replies[array_rand($replies)],
                    'buttons' => ['Find jobs for me', 'Analyze My Resume'],
                ];
            },
        ],

        // ─── CAREER ADVICE ───
        'career_advice' => [
            ['career', 'advice', 'suggestion', 'tip', 'what should i', 'how to improve', 'guidance', 'path', 'roadmap', 'future', 'growth'],
            function ($u, $c, $conv) use ($skill_list) {
                $reply = "Here's some career advice tailored to you";
                if (!empty($skill_list)) {
                    $reply .= " (skills: " . implode(', ', array_slice($skill_list, 0, 4)) . ")";
                }
                $reply .= ":<br><br>";
                $reply .= "<strong>1. Skill focus:</strong> ";
                if (!empty($skill_list)) {
                    $reply .= "Your " . $skill_list[0] . " skills are valuable. Keep them sharp with grooming videos and consider adding complementary skills.";
                } else {
                    $reply .= "Add your skills to your profile so I can give you more targeted advice.";
                }
                $reply .= "<br><strong>2. Applications:</strong> Quality over quantity - tailor each application.<br>";
                $reply .= "<strong>3. Interviews:</strong> Practice regularly. Even 10 minutes a day makes a difference.<br>";
                $reply .= "<strong>4. Network:</strong> Engage on professional platforms and attend industry events.";

                $conv['topics'][] = 'career_advice';
                return [
                    'reply' => $reply,
                    'buttons' => ['Grooming Coach', 'Start Mock Interview', 'Analyze My Resume'],
                ];
            },
        ],

        // ─── WHAT CAN YOU DO ───
        'capabilities' => [
            ['what can you do', 'features', 'menu', 'options', 'capabil', 'help', 'commands', 'things', 'anything else'],
            function ($u, $c, $conv) {
                $reply = "I'm Nova, your AI career assistant! Here's what I can help with:<br><br>";
                $reply .= "<strong>Job Discovery</strong> - Find jobs matched to your skills with AI match scores<br>";
                $reply .= "<strong>Resume Analysis</strong> - Score your profile and get improvement tips<br>";
                $reply .= "<strong>Cover Letters</strong> - Generate tailored cover letters in seconds<br>";
                $reply .= "<strong>Interview Prep</strong> - Practice with AI-scored mock interviews<br>";
                $reply .= "<strong>Grooming Coach</strong> - Personalized learning plans for skill gaps<br>";
                $reply .= "<strong>Career Advice</strong> - Get tips and guidance for your career path<br><br>";
                $reply .= "Just type naturally or use the quick buttons below!";

                $conv['topics'][] = 'capabilities';
                return [
                    'reply' => $reply,
                    'buttons' => ['Find jobs for me', 'Analyze My Resume', 'Start Mock Interview', 'Grooming Coach'],
                ];
            },
        ],

        // ─── SALARY / COMPENSATION ───
        'salary' => [
            ['salary', 'compensation', 'pay', 'earning', 'income', 'how much', 'bdt', 'taka', 'lakh'],
            function ($u, $c, $conv) {
                $reply = "Salary expectations depend on role, experience, and location. ";
                $reply .= "In Bangladesh's tech sector, entry-level positions typically range from 25,000-45,000 BDT/month, ";
                $reply .= "while mid-level roles can go up to 80,000-120,000 BDT/month depending on skills and company size. ";
                $reply .= "Check individual job listings for salary ranges - many employers now include them.";

                $conv['topics'][] = 'salary';
                return [
                    'reply' => $reply,
                    'buttons' => ['Browse Jobs', 'Find jobs for me', 'Career Advice'],
                ];
            },
        ],

        // ─── GOODBYE ───
        'goodbye' => [
            ['bye', 'goodbye', 'see you', 'later', 'talk later', 'gotta go', 'im leaving', 'see ya'],
            function ($u, $c, $conv) use ($name) {
                $goodbyes = [
                    "Goodbye, $name! Good luck with everything. I'll be here whenever you need me!",
                    "See you later! Remember, every application is a step closer to your dream job. You've got this!",
                    "Take care! Don't hesitate to come back anytime. I'm always here to help.",
                ];
                return [
                    'reply' => $goodbyes[array_rand($goodbyes)],
                    'buttons' => [],
                ];
            },
        ],

        // ─── WHO ARE YOU ───
        'identity' => [
            ['who are you', 'what are you', 'your name', 'tell me about yourself', 'about you', 'nova'],
            function ($u, $c, $conv) {
                $reply = "I'm <strong>Nova</strong>, your AI career assistant built into NovaHire. ";
                $reply .= "I combine rule-based intelligence with optional LLM capabilities to help you navigate your job search. ";
                $reply .= "I know about your profile, the current job listings, and the job market. ";
                $reply .= "Think of me as your personal career coach, available 24/7. What can I help you with?";

                $conv['topics'][] = 'identity';
                return [
                    'reply' => $reply,
                    'buttons' => ['Find jobs for me', 'What can you do?', 'Career Advice'],
                ];
            },
        ],

        // ─── COMPANY / EMPLOYER ───
        'company' => [
            ['company', 'employer', 'recruiter', 'about company', 'which company', 'top company', 'best company'],
            function ($u, $c, $conv) {
                $reply = "NovaHire partners with leading companies across Bangladesh. ";
                $reply .= "When you browse jobs, you'll see company profiles, reviews, and ratings. ";
                $reply .= "Each job listing shows the AI match score so you can prioritize the best fits. ";
                $reply .= "You can also save companies you're interested in to track their new openings.";

                $conv['topics'][] = 'company';
                return [
                    'reply' => $reply,
                    'buttons' => ['Browse Jobs', 'Find jobs for me', 'Saved Jobs'],
                ];
            },
        ],

        // ─── NOTIFICATIONS ───
        'notifications' => [
            ['notification', 'alert', 'notify', 'message', 'inbox', 'update'],
            function ($u, $c, $conv) {
                $reply = "You can check your notifications from the bell icon in the top navigation bar. ";
                $reply .= "NovaHire sends alerts for new job matches, application updates, assessment results, and messages from employers. ";
                $reply .= "Make sure your notification preferences are set so you don't miss opportunities!";

                $conv['topics'][] = 'notifications';
                return [
                    'reply' => $reply,
                    'buttons' => ['My Applications', 'Find jobs for me'],
                ];
            },
        ],

        // ─── QUIZ / ASSESSMENT ───
        'quiz' => [
            ['quiz', 'assessment', 'test', 'exam', 'pass', 'fail', 'score'],
            function ($u, $c, $conv) {
                $passed = isset($c['quiz_passed']) ? intval($c['quiz_passed']) : 0;
                $reply = "Assessment quizzes test your knowledge for specific job categories. ";

                if ($passed > 0) {
                    $reply .= "You've passed <strong>{$passed} quiz(es)</strong> so far - nice work! ";
                    $reply .= "Passing more quizzes unlocks more job application opportunities.";
                } else {
                    $reply .= "Some jobs require you to pass a quiz before you can apply. It's a way for employers to verify your skills. ";
                    $reply .= "Don't worry - the grooming videos help you prepare, and you can retake quizzes.";
                }

                $reply .= " The AI Mock Interview is great practice for the real thing.";

                $conv['topics'][] = 'quiz';
                return [
                    'reply' => $reply,
                    'buttons' => ['Start Mock Interview', 'Grooming Coach', 'Browse Jobs'],
                ];
            },
        ],

        // ─── WORK EXPERIENCE ───
        'experience' => [
            ['experience', 'fresher', 'entry level', 'senior', 'junior', 'years of experience', 'work history', 'internship'],
            function ($u, $c, $conv) {
                $reply = "Experience level affects your job matches. ";
                $reply .= "Whether you're a fresher or experienced professional, NovaHire has opportunities for you. ";
                $reply .= "Fresher? Focus on assessments and grooming to build credibility. ";
                $reply .= "Experienced? Highlight your achievements and keep your resume updated. ";
                $reply .= "Either way, I can help you find the right matches.";

                $conv['topics'][] = 'experience';
                return [
                    'reply' => $reply,
                    'buttons' => ['Find jobs for me', 'Analyze My Resume', 'Grooming Coach'],
                ];
            },
        ],

        // ─── CONTACT / SUPPORT ───
        'support' => [
            ['contact', 'support', 'help me', 'problem', 'issue', 'bug', 'not working', 'error'],
            function ($u, $c, $conv) {
                $reply = "I'm here to help! If you're experiencing a technical issue, try refreshing the page first. ";
                $reply .= "For account-related issues, check the FAQ section or reach out through the Contact page. ";
                $reply .= "For urgent matters, you can also message the NovaHire support team directly.";

                $conv['topics'][] = 'support';
                return [
                    'reply' => $reply,
                    'buttons' => ['View FAQs', 'Contact Support', 'What can you do?'],
                ];
            },
        ],

        // ─── NETWORKING ───
        'networking' => [
            ['network', 'networking', 'connect', 'linkedin', 'referral', 'reference'],
            function ($u, $c, $conv) {
                $reply = "Networking is one of the most effective job search strategies. ";
                $reply .= "On NovaHire, you can connect with companies through the messaging system. ";
                $reply .= "Beyond the platform, consider building your professional network on LinkedIn, ";
                $reply .= "attending industry meetups, and reaching out to alumni. Many jobs are filled through referrals!";

                $conv['topics'][] = 'networking';
                return [
                    'reply' => $reply,
                    'buttons' => ['Browse Jobs', 'Find jobs for me', 'Career Advice'],
                ];
            },
        ],

        // ─── SALARY NEGOTIATION ───
        'negotiation' => [
            ['negotiate', 'negotiation', 'bargain', 'ask for more', 'counter offer', 'higher salary'],
            function ($u, $c, $conv) {
                $reply = "Salary negotiation is a skill worth mastering! Here are key tips:<br><br>";
                $reply .= "<strong>1.</strong> Research market rates for your role and experience level<br>";
                $reply .= "<strong>2.</strong> Always have a number in mind (aim 10-20% above your minimum)<br>";
                $reply .= "<strong>3.</strong> Focus on value you bring, not personal needs<br>";
                $reply .= "<strong>4.</strong> Consider the whole package (benefits, growth, flexibility)<br>";
                $reply .= "<strong>5.</strong> Get the offer in writing before negotiating<br><br>";
                $reply .= "Confidence + preparation = better outcomes!";

                $conv['topics'][] = 'negotiation';
                return [
                    'reply' => $reply,
                    'buttons' => ['Career Advice', 'Find jobs for me'],
                ];
            },
        ],

    ];

    // ── Match intent ──
    foreach ($intents as $intent => $cfg) {
        if (ai_msg_contains($msg, $cfg[0])) {
            $result = $cfg[1]($user, $context, $conv);
            $result['intent'] = $intent;
            $conv['topics'][] = $intent;
            $conv['last_intent'] = $intent;
            return $result;
        }
    }

    // ── LLM fallback for open-ended questions ──
    if (ai_llm_available()) {
        $profile = 'The user is a job seeker named ' . (isset($user['username']) ? $user['username'] : 'unknown') .
            ' with skills: ' . ($skills ?: 'none listed') . '. ';
        $profile .= 'They have ' . ($context['applications_count'] ?? 0) . ' applications and ' . ($context['saved_count'] ?? 0) . ' saved jobs. ';
        $conv_context = !empty($conv['topics']) ? 'Recent conversation topics: ' . implode(', ', array_slice(array_unique($conv['topics']), -5)) . '.' : '';
        $system = 'You are Nova, the friendly AI career assistant of NovaHire, a job portal for Bangladesh with AI job matching, grooming video training, quizzes, and mock interviews. Keep answers short (max 90 words), helpful, and professional. Never invent personal data about the user. ' . $profile . $conv_context;
        $res = ai_llm_chat($system, $message, 250);
        if ($res['ok']) {
            $conv['topics'][] = 'llm';
            return ['reply' => $res['text'], 'intent' => 'llm', 'buttons' => []];
        }
    }

    // ── Smart fallback with context awareness ──
    return ai_smart_fallback($msg, $user, $context, $conv);
}

/**
 * Detect user sentiment from message text.
 */
function ai_detect_sentiment($msg) {
    $positive = ['great', 'awesome', 'love', 'amazing', 'excellent', 'perfect', 'wonderful', 'happy', 'excited', 'fantastic'];
    $negative = ['frustrated', 'angry', 'hate', 'terrible', 'awful', 'bad', 'worst', 'annoyed', 'upset', 'disappointed'];
    foreach ($positive as $w) { if (strpos($msg, $w) !== false) return 'positive'; }
    foreach ($negative as $w) { if (strpos($msg, $w) !== false) return 'negative'; }
    return 'neutral';
}

/**
 * Generate context-aware quick action buttons.
 */
function ai_suggest_buttons($user, $context, $intent) {
    $buttons = [];
    $apps = isset($context['applications_count']) ? intval($context['applications_count']) : 0;
    $score = isset($context['resume_score']) ? intval($context['resume_score']) : 0;

    if ($score < 60) $buttons[] = 'Analyze My Resume';
    if ($apps < 3) $buttons[] = 'Find jobs for me';
    $buttons[] = 'Start Mock Interview';
    $buttons[] = 'What can you do?';

    return array_slice(array_unique($buttons), 0, 4);
}

/**
 * Smart fallback that tries to be helpful based on context.
 */
function ai_smart_fallback($msg, $user, $context, $conv) {
    // If they typed a question mark, try to be helpful
    if (substr($msg, -1) === '?' || strpos($msg, 'how') !== false || strpos($msg, 'what') !== false || strpos($msg, 'why') !== false || strpos($msg, 'where') !== false || strpos($msg, 'when') !== false || strpos($msg, 'which') !== false) {
        $reply = "That's a great question! I'm not sure about that specific topic, but here's what I can definitely help with: ";
        $reply .= "job recommendations, resume improvements, interview practice, cover letters, and career advice. ";
        $reply .= "Try asking something like 'find jobs for me' or 'analyze my resume' and I'll give you detailed, personalized help.";

        return [
            'reply' => $reply,
            'intent' => 'fallback',
            'buttons' => ['Find jobs for me', 'Analyze My Resume', 'What can you do?'],
        ];
    }

    // If they seem to be describing something
    if (strlen($msg) > 20) {
        $reply = "I appreciate you sharing that! While I'm best at helping with specific tasks like job matching, resume analysis, and interview prep, ";
        $reply .= "I want to make sure I give you the most useful help. Could you try asking about one of these areas? ";
        $reply .= "I'll give you a much better, more detailed response.";

        return [
            'reply' => $reply,
            'intent' => 'fallback',
            'buttons' => ['Find jobs for me', 'Analyze My Resume', 'Start Mock Interview', 'Career Advice'],
        ];
    }

    // General fallback
    $fallbacks = [
        "I'm not quite sure what you mean, but I'm great at helping with jobs, resumes, interviews, and career growth. Try asking about any of those!",
        "Hmm, I didn't catch that. I can help you find jobs, analyze your resume, practice interviews, or give career advice. What would you like to try?",
        "I want to help! My specialties are job matching, resume analysis, interview prep, and career guidance. Pick one and I'll give you personalized assistance!",
    ];

    return [
        'reply' => $fallbacks[array_rand($fallbacks)],
        'intent' => 'fallback',
        'buttons' => ['Find jobs for me', 'What can you do?', 'Analyze My Resume'],
    ];
}
