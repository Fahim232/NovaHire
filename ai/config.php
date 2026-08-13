<?php
/**
 * NovaHire AI — Configuration & Settings Loader
 * 
 * Hybrid AI engine:
 *  - Rule-based features always work (matching, resume analysis, mock interview,
 *    grooming coach, chatbot fallback) — no API key required.
 *  - Optional LLM provider (OpenAI / Google Gemini) unlocks richer generation
 *    (cover letters, open-ended chat, smart job descriptions).
 *
 * Settings are stored in the `ai_settings` DB table and editable from
 * the admin panel (admin/ai_settings.php). A file-based fallback is used
 * when the table does not exist yet.
 */

if (defined('AI_CONFIG_LOADED')) return;
define('AI_CONFIG_LOADED', true);

// Base path for the ai/ folder
if (!defined('AI_DIR')) define('AI_DIR', __DIR__);
if (!defined('AI_ROOT')) define('AI_ROOT', dirname(__DIR__));

// ── Default fallback settings (used only if DB table missing) ──
$AI_DEFAULTS = [
    'provider'          => 'openai',          // openai | gemini | none
    'openai_api_key'    => '',
    'openai_model'      => 'gpt-3.5-turbo',
    'gemini_api_key'    => '',
    'gemini_model'      => 'gemini-1.5-flash',
    'llm_enabled'       => '1',               // 1 = use LLM when key present, 0 = force offline
    'chatbot_name'      => 'AI Assistant',
    'request_timeout'   => '30',
];

// ── Load settings from DB (preferred) ──
$ai_settings = $AI_DEFAULTS;
try {
    if (!isset($con)) {
        $con_file = AI_ROOT . '/admin/dbcon.php';
        if (is_file($con_file)) include $con_file;
    }
    if (isset($con) && $con) {
        $check = @mysqli_query($con, "SHOW TABLES LIKE 'ai_settings'");
        if ($check && mysqli_num_rows($check) > 0) {
            $rs = mysqli_query($con, "SELECT setting_key, setting_value FROM ai_settings");
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $ai_settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
} catch (Throwable $e) {
    // silent — fall back to defaults
}

define('AI_PROVIDER',      $ai_settings['provider']);
define('AI_OPENAI_KEY',    trim($ai_settings['openai_api_key']));
define('AI_OPENAI_MODEL',  $ai_settings['openai_model']);
define('AI_GEMINI_KEY',    trim($ai_settings['gemini_api_key']));
define('AI_GEMINI_MODEL',  $ai_settings['gemini_model']);
define('AI_LLM_ENABLED',   ($ai_settings['llm_enabled'] == '1'));
define('AI_CHATBOT_NAME',  $ai_settings['chatbot_name']);
define('AI_TIMEOUT',       intval($ai_settings['request_timeout']));

/**
 * Check whether a live LLM provider is currently configured & usable.
 */
function ai_llm_available() {
    if (!AI_LLM_ENABLED) return false;
    if (AI_PROVIDER === 'openai' && AI_OPENAI_KEY !== '') return true;
    if (AI_PROVIDER === 'gemini' && AI_GEMINI_KEY !== '') return true;
    return false;
}

/**
 * Get the active provider name for display ("OpenAI" / "Google Gemini").
 */
function ai_provider_label() {
    if (AI_PROVIDER === 'gemini' && AI_GEMINI_KEY !== '') return 'Google Gemini';
    if (AI_PROVIDER === 'openai' && AI_OPENAI_KEY !== '') return 'OpenAI';
    return 'NovaHire AI Engine';
}
