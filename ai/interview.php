<?php
/**
 * NovaHire AI - Mock Interview & Practice Quiz
 * Generates category-based interview questions (from a built-in bank plus
 * the database question bank), scores short answers by keyword matching,
 * and returns feedback + tips.
 */

if (defined('AI_INTERVIEW_LOADED')) return;
define('AI_INTERVIEW_LOADED', true);

require_once __DIR__ . '/engine.php';

/**
 * Question bank keyed by category. Each question has keywords used for scoring.
 */
function ai_question_bank() {
    return array(
        'PHP' => array(
            array('q' => 'How does PHP handle sessions and why is session_start() needed?', 'k' => array('cookie', 'session', 'state', 'server', 'start')),
            array('q' => 'Explain how to prevent SQL injection in PHP.', 'k' => array('prepared', 'statement', 'parameter', 'pdo', 'mysqli', 'escape', 'bind')),
            array('q' => 'What is the difference between include and require in PHP?', 'k' => array('fatal', 'warning', 'error', 'execution', 'stop')),
            array('q' => 'How does OOP work in PHP? Explain classes, inheritance and visibility.', 'k' => array('class', 'object', 'extend', 'public', 'private', 'protected', 'inheritance')),
            array('q' => 'What are PHP superglobals and give an example use case.', 'k' => array('get', 'post', 'server', 'session', 'request', 'superglobal')),
        ),
        'Java' => array(
            array('q' => 'Explain the four pillars of OOP in Java.', 'k' => array('encapsulation', 'inheritance', 'polymorphism', 'abstraction')),
            array('q' => 'What is the difference between checked and unchecked exceptions?', 'k' => array('runtime', 'compile', 'exception', 'throwable', 'checked')),
            array('q' => 'How does the JVM work and what is bytecode?', 'k' => array('bytecode', 'virtual', 'platform', 'independent', 'interpreter', 'jvm')),
            array('q' => 'Explain the equals() and hashCode() contract.', 'k' => array('equal', 'hash', 'object', 'bucket', 'collection')),
            array('q' => 'What is the difference between abstract classes and interfaces?', 'k' => array('abstract', 'interface', 'multiple', 'inherit', 'method', 'implement')),
        ),
        'Python' => array(
            array('q' => 'What is the difference between a list and a tuple in Python?', 'k' => array('immutable', 'mutable', 'list', 'tuple', 'change')),
            array('q' => 'How do *args and **kwargs work in Python functions?', 'k' => array('tuple', 'dict', 'arguments', 'keyword', 'variable')),
            array('q' => 'Explain Python decorators and when you would use one.', 'k' => array('function', 'wrapper', 'modify', 'behaviour', 'decorator')),
            array('q' => 'What is a virtual environment and why use one?', 'k' => array('isolated', 'dependencies', 'packages', 'environment', 'version')),
            array('q' => 'How does the Python GIL affect multi-threaded programs?', 'k' => array('lock', 'thread', 'bytecode', 'cpu', 'parallel', 'global')),
        ),
        'Frontend' => array(
            array('q' => 'Explain the CSS box model.', 'k' => array('margin', 'padding', 'border', 'content', 'width')),
            array('q' => 'What is the difference between localStorage and sessionStorage?', 'k' => array('persist', 'session', 'expire', 'tab', 'storage', 'browser')),
            array('q' => 'What is the virtual DOM in React?', 'k' => array('memory', 'representation', 'reconcil', 'update', 'diff')),
            array('q' => 'Explain how event bubbling works in JavaScript.', 'k' => array('parent', 'child', 'propagate', 'target', 'bubble')),
            array('q' => 'What is Flexbox and give a use case.', 'k' => array('layout', 'align', 'justify', 'row', 'column', 'direction')),
        ),
        'DataScience' => array(
            array('q' => 'Explain the difference between supervised and unsupervised learning.', 'k' => array('labeled', 'unlabeled', 'target', 'train', 'clustering', 'supervised')),
            array('q' => 'What is overfitting and how do you prevent it?', 'k' => array('train', 'generaliz', 'regulariz', 'cross', 'validat', 'dropout')),
            array('q' => 'What is the bias-variance tradeoff?', 'k' => array('bias', 'variance', 'error', 'complex', 'underfit', 'overfit')),
            array('q' => 'How does a decision tree decide splits?', 'k' => array('gini', 'entropy', 'impurity', 'gain', 'feature', 'split')),
            array('q' => 'What is feature scaling and why is it important?', 'k' => array('scale', 'normalize', 'standard', 'distance', 'gradient', 'unit')),
        ),
        'Finance' => array(
            array('q' => 'Explain the difference between revenue and profit.', 'k' => array('cost', 'expense', 'income', 'revenue', 'profit')),
            array('q' => 'What is ROI and how is it calculated?', 'k' => array('return', 'invest', 'gain', 'cost', 'ratio')),
            array('q' => 'Describe the main components of a balance sheet.', 'k' => array('asset', 'liability', 'equity', 'balance', 'sheet')),
            array('q' => 'What is compound interest?', 'k' => array('principal', 'interest', 'compounding', 'rate', 'time')),
            array('q' => 'What is a cash flow statement used for?', 'k' => array('operating', 'investing', 'financing', 'liquidity', 'cash')),
        ),
        'HR' => array(
            array('q' => 'What is employee onboarding and why does it matter?', 'k' => array('integration', 'new hire', 'training', 'retention', 'orientation')),
            array('q' => 'How do you measure employee performance with KPIs?', 'k' => array('key', 'measure', 'metric', 'goal', 'target', 'kpi')),
            array('q' => 'What strategies reduce employee attrition?', 'k' => array('engagement', 'retention', 'satisfaction', 'culture', 'turnover')),
            array('q' => 'What is an HRIS and what does it do?', 'k' => array('software', 'data', 'records', 'payroll', 'system', 'hris')),
            array('q' => 'Explain the recruitment process from start to finish.', 'k' => array('sourcing', 'screen', 'interview', 'offer', 'hiring', 'candidate')),
        ),
        'Sales' => array(
            array('q' => 'What is a sales funnel and its key stages?', 'k' => array('awareness', 'interest', 'decision', 'action', 'lead', 'funnel')),
            array('q' => 'How do you handle a customer objection?', 'k' => array('listen', 'empathy', 'question', 'solution', 'objection')),
            array('q' => 'Explain the difference between B2B and B2C selling.', 'k' => array('business', 'consumer', 'decision', 'cycle', 'value', 'b2b')),
            array('q' => 'What is a USP and why is it important?', 'k' => array('unique', 'proposition', 'different', 'competitor', 'value')),
            array('q' => 'How do you use a CRM effectively?', 'k' => array('track', 'data', 'follow', 'pipeline', 'customer', 'crm')),
        ),
    );
}

/**
 * Get interview questions for a category.
 * @param string $category
 * @param int    $count
 * @param array  $dbQuestions  optional extra questions from company_job_questions
 * @return array list of ['id'=>int,'question'=>string,'keywords'=>array,'source'=>'bank'|'db']
 */
function ai_get_interview_questions($category, $count = 5, $dbQuestions = array()) {
    $bank = ai_question_bank();
    $all = array();
    $cat = $category;

    // Normalise aliases to the closest bank key
    $alias = array(
        'javascript' => 'Frontend',
        'UI/UX' => 'Frontend',
        'Media' => 'Sales',
        'Marketing' => 'Sales',
        'DB' => 'PHP',
        'Engineering' => 'Finance',
        'Healthcare' => 'HR',
        'Education' => 'HR',
        'Legal' => 'HR',
        'Logistics' => 'Sales',
        'Consulting' => 'Sales',
        'Retail' => 'Sales',
    );
    if (isset($alias[$cat])) $cat = $alias[$cat];

    if (isset($bank[$cat])) {
        foreach ($bank[$cat] as $i => $item) {
            $all[] = array('id' => 'b' . $i, 'question' => $item['q'], 'keywords' => $item['k'], 'source' => 'bank');
        }
    }

    // Add DB questions (per-job questions) as extra practice
    foreach ($dbQuestions as $dq) {
        $all[] = array('id' => 'd' . intval($dq['id']), 'question' => $dq['question'], 'keywords' => array(), 'source' => 'db');
    }

    // No bank match: use a generic set
    if (empty($all)) {
        $generic = array(
            array('q' => 'Tell me about yourself and your relevant experience.', 'k' => array('experience', 'skills', 'project', 'role')),
            array('q' => 'What are your key strengths for this role?', 'k' => array('skill', 'strength', 'deliver', 'result')),
            array('q' => 'Describe a challenge you solved at work.', 'k' => array('problem', 'solution', 'team', 'result')),
            array('q' => 'Where do you see yourself in 5 years?', 'k' => array('growth', 'learn', 'goal', 'career')),
            array('q' => 'Why should we hire you?', 'k' => array('value', 'skill', 'fit', 'experience')),
        );
        foreach ($generic as $i => $item) {
            $all[] = array('id' => 'g' . $i, 'question' => $item['q'], 'keywords' => $item['k'], 'source' => 'bank');
        }
    }

    shuffle($all);
    return array_slice($all, 0, $count);
}

/**
 * Score a candidate answer against expected keywords.
 * @return array{score:int(0-100), found:array, missing:array, feedback:string, tips:array}
 */
function ai_score_answer($question, $keywords, $answer) {
    $answer = (string)$answer;
    $aLower = strtolower($answer);
    $found = array();
    $missing = array();
    foreach ($keywords as $kw) {
        $kwL = strtolower($kw);
        if (strpos($aLower, $kwL) !== false) {
            $found[] = $kw;
        } else {
            $missing[] = $kw;
        }
    }

    $base = count($keywords) > 0 ? round((count($found) / count($keywords)) * 100) : 50;
    // length heuristic
    $words = count(preg_split('/\s+/', trim($answer)));
    if ($words < 8) $base = max(10, $base - 25);
    elseif ($words < 20) $base = min(95, $base + 5);
    $score = max(0, min(100, $base));

    if ($score >= 80) {
        $feedback = 'Strong answer! You covered the core concepts clearly.';
    } elseif ($score >= 55) {
        $feedback = 'Good attempt. Your answer is on the right track but misses a few key points.';
    } elseif ($score >= 30) {
        $feedback = 'Fair answer. Try to structure it with a clear definition and an example.';
    } else {
        $feedback = 'This needs more work. Review the grooming videos on this topic and try again.';
    }

    $tips = array();
    if (!empty($missing)) {
        $tips[] = 'Mention these concepts: ' . implode(', ', $missing) . '.';
    }
    $tips[] = 'Use the STAR method: Situation, Task, Action, Result.';
    $tips[] = 'Give a concrete example from a project or internship.';

    return array(
        'score'    => $score,
        'found'    => $found,
        'missing'  => $missing,
        'feedback' => $feedback,
        'tips'     => $tips,
    );
}

/**
 * Personalised interview tips based on the user's profile.
 */
function ai_interview_tips($user) {
    $tips = array();
    $skills = isset($user['user_skills']) ? ai_skills_to_array($user['user_skills']) : array();
    if (!empty($skills)) {
        $tips[] = 'Be ready to demonstrate: ' . implode(', ', array_slice($skills, 0, 4)) . '.';
    }
    $tips[] = 'Prepare 2-3 success stories from your past experience using the STAR method.';
    $tips[] = 'Research the company before the interview and prepare 2-3 smart questions to ask.';
    $tips[] = 'Practice answering out loud - it improves confidence and fluency.';
    return $tips;
}
