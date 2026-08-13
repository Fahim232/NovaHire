<?php
/**
 * NovaHire AI — Core Hybrid Engine
 *
 * Provides the LLM provider abstraction (OpenAI / Gemini / offline) plus a
 * collection of text-processing helpers used by every AI feature. The offline
 * (rule-based) path is the default and needs no external service.
 */

if (defined('AI_ENGINE_LOADED')) return;
define('AI_ENGINE_LOADED', true);

require_once __DIR__ . '/config.php';

/* ════════════════════════════════════════════════════════════════
   LLM PROVIDER LAYER
   ════════════════════════════════════════════════════════════════ */

/**
 * Send a prompt to the configured LLM provider.
 *
 * @param string $system   System instruction.
 * @param string $prompt   User prompt / context.
 * @param int    $max_tokens
 * @return array{ok:bool, text:string, error:string}
 */
function ai_llm_chat($system, $prompt, $max_tokens = 800) {
    if (AI_PROVIDER === 'gemini' && AI_GEMINI_KEY !== '') {
        return ai_gemini_chat($system, $prompt, $max_tokens);
    }
    if (AI_PROVIDER === 'openai' && AI_OPENAI_KEY !== '') {
        return ai_openai_chat($system, $prompt, $max_tokens);
    }
    return ['ok' => false, 'text' => '', 'error' => 'No LLM provider configured.'];
}

/**
 * OpenAI Chat Completions API call.
 */
function ai_openai_chat($system, $prompt, $max_tokens = 800) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = [
        'model'       => AI_OPENAI_MODEL,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $prompt],
        ],
        'max_tokens'  => $max_tokens,
        'temperature' => 0.7,
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_OPENAI_KEY,
    ];
    return ai_http_post_json($url, $payload, $headers, function ($json) {
        if (!empty($json['choices'][0]['message']['content'])) {
            return ['ok' => true, 'text' => trim($json['choices'][0]['message']['content']), 'error' => ''];
        }
        $err = isset($json['error']['message']) ? $json['error']['message'] : 'Empty OpenAI response.';
        return ['ok' => false, 'text' => '', 'error' => $err];
    });
}

/**
 * Google Gemini generateContent API call.
 */
function ai_gemini_chat($system, $prompt, $max_tokens = 800) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(AI_GEMINI_MODEL) . ':generateContent?key=' . AI_GEMINI_KEY;
    $payload = [
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'maxOutputTokens'   => $max_tokens,
            'temperature'       => 0.7,
        ],
    ];
    return ai_http_post_json($url, $payload, ['Content-Type: application/json'], function ($json) {
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text !== '') {
            return ['ok' => true, 'text' => trim($text), 'error' => ''];
        }
        $err = $json['error']['message'] ?? 'Empty Gemini response.';
        return ['ok' => false, 'text' => '', 'error' => $err];
    });
}

/**
 * Low-level JSON POST helper using cURL with a stream fallback.
 *
 * @param callable $parse  function(array $json): array
 */
function ai_http_post_json($url, $payload, $headers, $parse) {
    $body = json_encode($payload);
    $response = null;

    // Preferred: cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => AI_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'text' => '', 'error' => 'cURL error: ' . $curl_err];
        }
        $json = json_decode($response, true);
        if (!is_array($json)) {
            return ['ok' => false, 'text' => '', 'error' => 'Invalid JSON response from provider (HTTP ' . ($curl_info['http_code'] ?? '?') . ').'];
        }
        return $parse($json);
    }

    // Fallback: allow_url_fopen stream context
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => AI_TIMEOUT,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['ok' => false, 'text' => '', 'error' => 'Unable to reach the LLM provider.'];
        }
        $json = json_decode($response, true);
        if (!is_array($json)) {
            return ['ok' => false, 'text' => '', 'error' => 'Invalid response from provider.'];
        }
        return $parse($json);
    }

    return ['ok' => false, 'text' => '', 'error' => 'cURL and allow_url_fopen are both disabled.'];
}

/* ════════════════════════════════════════════════════════════════
   TEXT HELPERS
   ════════════════════════════════════════════════════════════════ */

/**
 * Normalise a skill/word: lowercase, trim, strip common suffixes.
 */
function ai_norm($str) {
    $s = mb_strtolower(trim((string)$str));
    $s = preg_replace('/[^a-z0-9+#._ -]/', '', $s);
    return preg_replace('/\s+/', ' ', $s);
}

/**
 * Tokenize a comma/newline separated skill list.
 */
function ai_skills_to_array($skills_str) {
    $out = [];
    foreach (preg_split('/[,\n;]/', (string)$skills_str) as $item) {
        $item = ai_norm($item);
        if ($item !== '' && !in_array($item, $out)) {
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * Fuzzy word match with substring + Levenshtein tolerance.
 */
function ai_skill_matches($user_skill, $job_text) {
    $u = ai_norm($user_skill);
    if ($u === '' || $u === null) return false;
    $t = ai_norm($job_text);
    if (strlen($u) > 2 && strpos($t, $u) !== false) return true;
    // short tokens need word boundaries
    if (preg_match('/\b' . preg_quote($u, '/') . '\b/', $t)) return true;
    // Levenshtein tolerance for typos (>= 5 char words)
    foreach (preg_split('/\s+/', $t) as $word) {
        if (strlen($u) >= 5 && strlen($word) >= 5 && levenshtein($u, $word) <= 1) {
            return true;
        }
    }
    return false;
}

/**
 * Extract approximate years of experience from a free-text string.
 */
function ai_parse_years($str) {
    $s = strtolower((string)$str);
    if (preg_match('/(\d+)\s*(?:\+|plus)?\s*(?:years|yrs|yr)/', $s, $m)) return intval($m[1]);
    if (preg_match('/\b(?:fresher|entry[- ]?level|none|0)\b/', $s)) return 0;
    return null; // unknown
}

/**
 * Best-effort keyword-based category guess from a free-text string.
 * Returns one of the known grooming/job categories or null.
 */
function ai_detect_category($text) {
    $t = strtolower((string)$text);
    $map = [
        'PHP'          => ['php', 'laravel', 'wordpress', 'mysql'],
        'Java'         => ['java', 'spring', 'hibernate', 'jvm'],
        'Python'       => ['python', 'django', 'flask', 'pandas'],
        'Frontend'     => ['frontend', 'front-end', 'react', 'javascript', 'css', 'html', 'vue', 'angular'],
        'UI/UX'        => ['ui/ux', 'ui design', 'ux design', 'figma', 'wireframe'],
        'Finance'      => ['finance', 'accounting', 'roi', 'analyst', 'bank'],
        'Healthcare'   => ['healthcare', 'nurse', 'medical', 'clinical', 'health'],
        'Education'    => ['education', 'teacher', 'teaching', 'tutor'],
        'Engineering'  => ['engineer', 'civil', 'mechanical', 'electrical'],
        'Sales'        => ['sales', 'marketing', 'b2b', 'crm', 'cold call'],
        'HR'           => ['hr ', 'human resource', 'recruit', 'hr coordinator', 'hris'],
        'Legal'        => ['legal', 'law', 'attorney', 'compliance'],
        'Media'        => ['media', 'seo', 'content', 'marketing', 'ctr'],
        'Logistics'    => ['logistics', 'supply chain', 'inventory', 'warehouse'],
        'Consulting'   => ['consult', 'swot', 'strategy', 'stakeholder'],
        'Retail'       => ['retail', 'store manager', 'pos ', 'sku'],
        'DataScience'  => ['data science', 'machine learning', 'ai ', 'ml ', 'analytics'],
        'DB'           => ['database', 'sql', 'postgres', 'mongodb', 'dba'],
        'Marketing'    => ['marketing', 'brand', 'social media', 'seo'],
    ];
    $best = null;
    $best_score = 0;
    foreach ($map as $cat => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (strpos($t, $kw) !== false) $score++;
        }
        if ($score > $best_score) {
            $best_score = $score;
            $best = $cat;
        }
    }
    return $best;
}

/**
 * Compute a "readiness" label from a 0-100 score.
 */
function ai_readiness_label($score) {
    if ($score >= 85) return ['Strong Match', '#059669'];
    if ($score >= 70) return ['Good Match', '#2563eb'];
    if ($score >= 50) return ['Fair Match', '#d97706'];
    if ($score >= 30) return ['Weak Match', '#ea580c'];
    return ['Low Match', '#dc2626'];
}

/**
 * Guard against very long input truncation.
 */
function ai_clip($str, $len = 4000) {
    $str = (string)$str;
    return strlen($str) > $len ? substr($str, 0, $len) . '…' : $str;
}
