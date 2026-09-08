-- ============================================================
-- NovaHire AI - Database Tables
-- Import into the `projects` database (same DB as database.sql)
-- ============================================================

-- AI Engine settings (editable from admin/ai_settings.php)
CREATE TABLE IF NOT EXISTS `ai_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ai_settings` (`setting_key`, `setting_value`) VALUES
('provider', 'openai'),
('openai_api_key', ''),
('openai_model', 'gpt-3.5-turbo'),
('gemini_api_key', ''),
('gemini_model', 'gemini-1.5-flash'),
('llm_enabled', '1'),
('chatbot_name', 'AI Assistant'),
('request_timeout', '30')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- Chatbot conversation history per user
CREATE TABLE IF NOT EXISTS `ai_chat_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `message` text NOT NULL,
  `intent` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Generated cover letters
CREATE TABLE IF NOT EXISTS `ai_cover_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `mode` varchar(20) NOT NULL DEFAULT 'template',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resume analysis snapshots
CREATE TABLE IF NOT EXISTS `ai_resume_analyses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mock interview practice attempts
CREATE TABLE IF NOT EXISTS `ai_mock_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `user_answer` text DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job recommendation log (match scores per user/job)
CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `matched_skills` text DEFAULT NULL,
  `missing_skills` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
