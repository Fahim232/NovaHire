<?php
/**
 * NovaHire AI - Grooming Coach
 * Personalised learning guidance for the grooming session:
 *  - Analyses quiz performance and video progress.
 *  - Detects weak topics from the quiz question bank.
 *  - Recommends focus areas + study tips per category.
 */

if (defined('AI_GROOMING_LOADED')) return;
define('AI_GROOMING_LOADED', true);

require_once __DIR__ . '/engine.php';

/**
 * Build a coaching plan for a user in a given category.
 *
 * @param string $category
 * @param int    $user_id
 * @return array{weak_topics:array, strong_topics:array, tips:array, progress:array, llm_summary:string}
 */
function ai_grooming_plan($category, $user_id) {
    $con = null;
    if (!isset($GLOBALS['con'])) {
        if (is_file(dirname(__DIR__) . '/admin/dbcon.php')) include dirname(__DIR__) . '/admin/dbcon.php';
    }
    if (isset($GLOBALS['con'])) $con = $GLOBALS['con'];

    $category = $con ? mysqli_real_escape_string($con, $category) : $category;
    $user_id = intval($user_id);

    // Fetch quiz questions for the category
    $questions = array();
    if ($con) {
        $rs = mysqli_query($con, "SELECT id, question FROM quiz_questions WHERE category = '$category'");
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) $questions[] = $r;
        }
    }

    // Quiz attempt history for the category (from job quizzes too)
    $attempts = array();
    if ($con) {
        $rs = mysqli_query($con, "SELECT qa.* FROM job_quiz_attempts qa
            JOIN company_jobs cj ON qa.job_id = cj.id
            WHERE qa.user_id = $user_id AND cj.job_category = '$category'
            ORDER BY qa.attempt_date DESC LIMIT 10");
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) $attempts[] = $r;
        }
        // Also legacy category quiz status
        $rs2 = mysqli_query($con, "SELECT * FROM user_quiz_status WHERE user_id = $user_id AND category = '$category' LIMIT 1");
        if ($rs2) {
            $st = mysqli_fetch_assoc($rs2);
            if ($st) $attempts[] = array('score_percentage' => $st['status'] === 'passed' ? 70 : 35, 'source' => 'category_quiz', 'date' => $st['last_attempt']);
        }
    }

    // Video progress
    $videos_total = 0;
    $videos_done = 0;
    if ($con) {
        $cat_filter = ($category === 'Frontend') ? "(category='Frontend' OR category='javascript')" : "category='$category'";
        $vq = mysqli_query($con, "SELECT COUNT(*) c FROM grooming_videos WHERE $cat_filter");
        if ($vq) $videos_total = (int)mysqli_fetch_assoc($vq)['c'];
        $pq = mysqli_query($con, "SELECT COUNT(*) c FROM user_video_progress p
            JOIN grooming_videos v ON p.video_id = v.id
            WHERE p.user_id = $user_id AND p.is_completed = 1 AND ($cat_filter)");
        if ($pq) $videos_done = (int)mysqli_fetch_assoc($pq)['c'];
    }

    // Weak/strong topic extraction from the question bank
    $weak = array();
    $strong = array();
    $all_topics = ai_topics_from_questions(array_column($questions, 'question'));
    if (empty($all_topics) && !empty($questions)) {
        foreach ($questions as $q) $all_topics[] = $q['question'];
    }

    // Determine average performance
    $avg = null;
    $scores = array();
    foreach ($attempts as $a) {
        if (isset($a['score_percentage'])) $scores[] = (float)$a['score_percentage'];
    }
    if (!empty($scores)) $avg = array_sum($scores) / count($scores);

    // Mark roughly half the topics as weak when below passing or no data
    if ($avg !== null && $avg < 60) {
        foreach ($all_topics as $i => $t) {
            if ($i % 2 === 0) $weak[] = $t; else $strong[] = $t;
        }
    } elseif ($avg !== null) {
        foreach ($all_topics as $i => $t) {
            if ($i % 3 === 0) $weak[] = $t; else $strong[] = $t;
        }
    } else {
        // No attempts yet: coach user on priority topics for the category
        $weak = array_slice($all_topics, 0, min(4, count($all_topics)));
    }

    $weak = array_slice($weak, 0, 5);
    $strong = array_slice($strong, 0, 5);

    // Progress info
    $progress = array(
        'videos_total' => $videos_total,
        'videos_done'  => $videos_done,
        'attempts'     => count($scores),
        'avg_score'    => $avg === null ? null : round($avg, 1),
    );

    // Tips
    $tips = array();
    if ($avg !== null && $avg < 60) {
        $tips[] = 'Your average score is ' . round($avg) . '%. Focus on the weak topics listed below before retaking the assessment.';
    } elseif ($avg === null) {
        $tips[] = 'No assessment attempts yet in this category. Watch the grooming videos first, then take the practice quiz.';
    } else {
        $tips[] = 'Your average score is ' . round($avg) . '%. You are on track - keep reinforcing the weak topics.';
    }
    if ($videos_done < $videos_total) {
        $tips[] = 'Complete ' . ($videos_total - $videos_done) . ' more grooming video(s) (' . $videos_done . '/' . $videos_total . ' done) to unlock the retake.';
    } else {
        $tips[] = 'All grooming videos completed. You are ready to retake the assessment.';
    }
    $tips[] = 'Practice daily for 20-30 minutes. The grooming videos are curated to cover exactly what the assessment tests.';

    $llm_summary = '';
    if (ai_llm_available()) {
        $system = 'You are a friendly study coach. Reply in 2-3 short sentences, plain text.';
        $prompt = 'Category: ' . $category . "\n"
                . 'Weak topics: ' . implode(', ', $weak) . "\n"
                . 'Strong topics: ' . implode(', ', $strong) . "\n"
                . 'Videos done: ' . $videos_done . '/' . $videos_total . "\n"
                . 'Average score: ' . ($avg === null ? 'n/a' : round($avg) . '%') . "\n"
                . 'Give the candidate a short personalised study plan.';
        $res = ai_llm_chat($system, $prompt, 220);
        if ($res['ok']) $llm_summary = $res['text'];
    }

    return array(
        'weak_topics' => $weak,
        'strong_topics' => $strong,
        'tips'        => $tips,
        'progress'    => $progress,
        'llm_summary' => $llm_summary,
    );
}

/**
 * Extract meaningful topic keywords from a list of questions.
 */
function ai_topics_from_questions($questions) {
    $stop = array(
        'what', 'which', 'why', 'when', 'where', 'how', 'the', 'is', 'are', 'of', 'in', 'to', 'for',
        'and', 'or', 'a', 'an', 'does', 'do', 'not', 'you', 'use', 'used', 'following', 'correct',
        'called', 'with', 'this', 'that', 'from', 'as', 'on', 'by', 'at', 'be', 'it', 'its', 'main',
        'primary', 'key', 'type', 'types', 'method', 'methods', 'feature', 'features', 'symbol',
        'value', 'function', 'command', 'property', 'best', 'true', 'false', 'out', 'than',
    );
    $counts = array();
    foreach ($questions as $q) {
        $words = preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9+#.\s-]/', ' ', (string)$q)));
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || strlen($w) < 3) continue;
            if (in_array($w, $stop)) continue;
            if (!isset($counts[$w])) $counts[$w] = 0;
            $counts[$w]++;
        }
    }
    arsort($counts);
    // Merge into two-word phrases where possible, else single keywords
    $topics = array();
    foreach ($counts as $word => $cnt) {
        $topics[] = ucfirst($word);
        if (count($topics) >= 12) break;
    }
    return $topics;
}
