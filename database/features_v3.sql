-- ============================================================================
--  NovaHire — features_v3.sql
--  Growth & Monetization upgrade (additive migration)
--
--  Adds:  generalized payments, seeker "NovaHire Pro" subscriptions,
--         featured jobs, application pipeline, placements (success fee),
--         mentor marketplace (live grooming sessions), certificates,
--         and a mentor payout ledger.
--
--  Safe to run on top of the existing `database.sql`.
--  Uses CREATE TABLE IF NOT EXISTS and (MariaDB) ADD COLUMN IF NOT EXISTS,
--  so re-running it does no harm.
--
--  Run:  mysql -u root projects < features_v3.sql
--        (or import via phpMyAdmin into the `projects` database)
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- 1. PAYMENTS  (generalized: both companies AND job seekers pay)
--    Created here so a fresh install works; ALTERs below upgrade
--    an older `payments` table if it already exists.
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`             int(11) NOT NULL AUTO_INCREMENT,
  `payment_id`     varchar(64)  NOT NULL,
  `payer_type`     enum('company','user') NOT NULL DEFAULT 'company',
  `payer_id`       int(11) DEFAULT NULL COMMENT 'user_info.id or companies.id depending on payer_type',
  `company_id`     int(11) DEFAULT NULL COMMENT 'kept for backward compatibility',
  `purpose`        enum('subscription','pro_subscription','session','certificate','featured_job','placement_fee','other') NOT NULL DEFAULT 'subscription',
  `item_id`        int(11) DEFAULT NULL COMMENT 'session_id / job_id / certificate_id etc.',
  `plan_type`      varchar(50) DEFAULT NULL,
  `amount`         decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency`       varchar(10) NOT NULL DEFAULT 'BDT',
  `payment_method` varchar(30) NOT NULL DEFAULT 'sslcommerz',
  `status`         enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id` (`payment_id`),
  KEY `payer` (`payer_type`,`payer_id`),
  KEY `purpose` (`purpose`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade an existing (older) payments table in place — MariaDB supports IF NOT EXISTS.
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `payer_type` enum('company','user') NOT NULL DEFAULT 'company' AFTER `payment_id`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `payer_id` int(11) DEFAULT NULL AFTER `payer_type`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `purpose` enum('subscription','pro_subscription','session','certificate','featured_job','placement_fee','other') NOT NULL DEFAULT 'subscription' AFTER `company_id`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `item_id` int(11) DEFAULT NULL AFTER `purpose`;
ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `completed_at` timestamp NULL DEFAULT NULL;
ALTER TABLE `payments` MODIFY `company_id` int(11) DEFAULT NULL;

-- ============================================================
-- 2. COMPANY SUBSCRIPTIONS  (used by existing payment.php; created
--    here in case it was never set up)
-- ============================================================
CREATE TABLE IF NOT EXISTS `company_subscriptions` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `plan_type`  varchar(50) NOT NULL,
  `payment_id` varchar(64) DEFAULT NULL,
  `starts_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `status`     enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. USER SUBSCRIPTIONS  (NovaHire Pro — seeker premium plan)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `user_id`    int(11) NOT NULL,
  `plan_type`  varchar(50) NOT NULL DEFAULT 'pro',
  `payment_id` varchar(64) DEFAULT NULL,
  `starts_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `status`     enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. FEATURED JOBS  (paid boost) — extend company_jobs
-- ============================================================
ALTER TABLE `company_jobs` ADD COLUMN IF NOT EXISTS `is_featured` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `company_jobs` ADD COLUMN IF NOT EXISTS `featured_until` datetime DEFAULT NULL;

-- ============================================================
-- 5. APPLICATION PIPELINE  — extend job_applications
--    (keeps the original application_status; adds richer stages)
-- ============================================================
ALTER TABLE `job_applications` ADD COLUMN IF NOT EXISTS `pipeline_stage` enum('applied','reviewed','shortlisted','interview','offered','hired','rejected') NOT NULL DEFAULT 'applied';
ALTER TABLE `job_applications` ADD COLUMN IF NOT EXISTS `stage_updated_at` datetime DEFAULT NULL;

-- ============================================================
-- 6. PLACEMENTS  (confirmed hires — basis for the success fee)
-- ============================================================
CREATE TABLE IF NOT EXISTS `placements` (
  `id`             int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) DEFAULT NULL,
  `job_id`         int(11) NOT NULL,
  `user_id`        int(11) NOT NULL,
  `company_id`     int(11) NOT NULL,
  `salary_amount`  decimal(12,2) DEFAULT NULL,
  `placement_fee`  decimal(12,2) NOT NULL DEFAULT 0.00,
  `fee_status`     enum('pending','invoiced','paid') NOT NULL DEFAULT 'pending',
  `hired_at`       date NOT NULL,
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `user_id` (`user_id`),
  KEY `fee_status` (`fee_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. MENTORS  (live grooming coaches — admin-approved role)
-- ============================================================
CREATE TABLE IF NOT EXISTS `mentors` (
  `id`             int(11) NOT NULL AUTO_INCREMENT,
  `name`           varchar(255) NOT NULL,
  `email`          varchar(255) NOT NULL,
  `phone`          varchar(20) DEFAULT NULL,
  `password`       varchar(255) NOT NULL,
  `category`       varchar(100) NOT NULL COMMENT 'primary expertise, matches job categories',
  `headline`       varchar(255) DEFAULT NULL,
  `bio`            text DEFAULT NULL,
  `hourly_rate`    decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'price per session (BDT)',
  `languages`      varchar(255) DEFAULT NULL,
  `photo`          varchar(255) DEFAULT NULL,
  `rating`         decimal(3,2) NOT NULL DEFAULT 0.00,
  `total_reviews`  int(11) NOT NULL DEFAULT 0,
  `total_sessions` int(11) NOT NULL DEFAULT 0,
  `status`         enum('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `category` (`category`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. MENTOR SLOTS  (bookable availability)
-- ============================================================
CREATE TABLE IF NOT EXISTS `mentor_slots` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `mentor_id`    int(11) NOT NULL,
  `slot_date`    date NOT NULL,
  `slot_time`    time NOT NULL,
  `duration_min` int(11) NOT NULL DEFAULT 45,
  `is_booked`    tinyint(1) NOT NULL DEFAULT 0,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mentor_id` (`mentor_id`),
  KEY `slot_date` (`slot_date`),
  FOREIGN KEY (`mentor_id`) REFERENCES `mentors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. GROOMING SESSIONS  (paid 1-on-1 bookings)
-- ============================================================
CREATE TABLE IF NOT EXISTS `grooming_sessions` (
  `id`             int(11) NOT NULL AUTO_INCREMENT,
  `mentor_id`      int(11) NOT NULL,
  `user_id`        int(11) NOT NULL,
  `slot_id`        int(11) DEFAULT NULL,
  `session_type`   enum('mock_interview','resume_review','career_coaching') NOT NULL DEFAULT 'mock_interview',
  `scheduled_at`   datetime NOT NULL,
  `duration_min`   int(11) NOT NULL DEFAULT 45,
  `price`          decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission`     decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'platform cut',
  `mentor_earning` decimal(10,2) NOT NULL DEFAULT 0.00,
  `meeting_link`   varchar(255) DEFAULT NULL,
  `status`         enum('pending_payment','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending_payment',
  `payment_id`     varchar(64) DEFAULT NULL,
  `mentor_notes`   text DEFAULT NULL,
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mentor_id` (`mentor_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`mentor_id`) REFERENCES `mentors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `user_info`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. SESSION REVIEWS
-- ============================================================
CREATE TABLE IF NOT EXISTS `session_reviews` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `user_id`    int(11) NOT NULL,
  `mentor_id`  int(11) NOT NULL,
  `rating`     tinyint(1) NOT NULL DEFAULT 5,
  `comment`    text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `one_review_per_session` (`session_id`),
  KEY `mentor_id` (`mentor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. MENTOR EARNINGS  (payout ledger)
-- ============================================================
CREATE TABLE IF NOT EXISTS `mentor_earnings` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `mentor_id`  int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `amount`     decimal(10,2) NOT NULL DEFAULT 0.00,
  `type`       enum('earning','payout') NOT NULL DEFAULT 'earning',
  `status`     enum('available','paid') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mentor_id` (`mentor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. CERTIFICATES  (verifiable skill certificates)
-- ============================================================
CREATE TABLE IF NOT EXISTS `certificates` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `user_id`    int(11) NOT NULL,
  `category`   varchar(100) NOT NULL,
  `title`      varchar(255) NOT NULL,
  `cert_code`  varchar(24) NOT NULL,
  `score`      int(11) DEFAULT NULL,
  `is_paid`    tinyint(1) NOT NULL DEFAULT 0,
  `payment_id` varchar(64) DEFAULT NULL,
  `issued_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cert_code` (`cert_code`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `user_info`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. NOTIFICATIONS / MESSAGES — allow 'mentor' as a party
-- ============================================================
ALTER TABLE `notifications` MODIFY `recipient_type` enum('user','company','admin','mentor') NOT NULL;
ALTER TABLE `notifications` MODIFY `sender_type`    enum('user','company','admin','mentor','system') NOT NULL DEFAULT 'system';

COMMIT;
