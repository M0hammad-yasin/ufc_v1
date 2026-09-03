-- United Five Construction, Inc.
-- Client Pre-Assessment Database Schema
-- Version 5.0 Specification

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `assessment_trackers`;
DROP TABLE IF EXISTS `email_logs`;
DROP TABLE IF EXISTS `assessment_history`;
DROP TABLE IF EXISTS `follow_up_tasks`;
DROP TABLE IF EXISTS `ceo_overrides`;
DROP TABLE IF EXISTS `evidence_files`;
DROP TABLE IF EXISTS `explain_blocks`;
DROP TABLE IF EXISTS `assessment_answers`;
DROP TABLE IF EXISTS `phase_results`;
DROP TABLE IF EXISTS `assessments`;
DROP TABLE IF EXISTS `question_options`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `phases`;
DROP TABLE IF EXISTS `tiers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'assessor', 'ceo') NOT NULL DEFAULT 'assessor',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tiers Table
CREATE TABLE `tiers` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `color`       VARCHAR(30)  DEFAULT '#c9a84c',
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Phases Table
CREATE TABLE `phases` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase_number` TINYINT UNSIGNED NOT NULL UNIQUE,
    `title` VARCHAR(150) NOT NULL,
    `the_question` VARCHAR(255) NOT NULL,
    `unlocks_when` VARCHAR(255) NOT NULL,
    `question_count` TINYINT UNSIGNED NOT NULL,
    `weight` DECIMAL(5,3) NOT NULL DEFAULT 0.000,
    `threshold` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Questions Table
CREATE TABLE `questions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phase_id` INT UNSIGNED NOT NULL,
    `question_number` VARCHAR(10) NOT NULL UNIQUE,
    `question_text` TEXT NOT NULL,
    `response_type` ENUM('YES_NO', 'YES_NO_NA', 'SCALE_1_10', 'SINGLE_SELECT', 'MULTI_SELECT') NOT NULL,
    `owner` ENUM('DESIGN', 'CLIENT', 'LENDER', 'UFC') NOT NULL,
    `visibility` ENUM('CLIENT_FACING', 'INTERNAL_ONLY') NOT NULL DEFAULT 'CLIENT_FACING',
    `trigger_type` ENUM('NONE', 'HOLD', 'STOP', 'ESCALATE') NOT NULL DEFAULT 'NONE',
    `client_message` TEXT,
    `evidence_required` TEXT,
    `display_condition` TEXT, -- JSON or condition string
    `order_index` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Question Options Table (For Single Select / Multi-Select checklist items)
CREATE TABLE `question_options` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT UNSIGNED NOT NULL,
    `option_key` VARCHAR(100) NOT NULL,
    `option_label` TEXT NOT NULL,
    `branch_action` VARCHAR(50) DEFAULT 'CONTINUE', -- CONTINUE, SKIP, HOLD, STOP, ESCALATE
    `target_question_number` VARCHAR(10) DEFAULT NULL,
    `score_weight` DECIMAL(4,2) DEFAULT 0.00,
    `order_index` INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Assessments Table
CREATE TABLE `assessments` (
    `id`                               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_number`                VARCHAR(30)   NOT NULL UNIQUE,
    `client_name`                      VARCHAR(150)  NOT NULL,
    `client_email`                     VARCHAR(150)  NOT NULL,
    `client_phone`                     VARCHAR(50)   DEFAULT NULL,
    `project_name`                     VARCHAR(255)  DEFAULT NULL UNIQUE,
    `tier_id`                          INT UNSIGNED  DEFAULT NULL,
    `project_address`                  TEXT          NOT NULL DEFAULT '',
    `project_type`                     VARCHAR(100)  DEFAULT NULL,
    `estimated_budget`                 DECIMAL(14,2) DEFAULT NULL,
    `assessor_id`                      INT UNSIGNED  NOT NULL,
    `current_phase`                    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `status`                           ENUM('IN_PROGRESS','HOLD','ESCALATED','PROCEED_TO_PROPOSAL','NOT_A_FIT') NOT NULL DEFAULT 'IN_PROGRESS',
    `decline_reason`                   ENUM('STOP_TRIGGER','UFC_CAPACITY','UNRESPONSIVE','OTHER') DEFAULT NULL,
    `decline_notes`                    TEXT          DEFAULT NULL,
    `hold_deadline_date`               DATE          DEFAULT NULL,
    `requirements_letter_generated_at` DATETIME      DEFAULT NULL,
    `decline_letter_generated_at`      DATETIME      DEFAULT NULL,
    `completed_at`                     DATETIME      DEFAULT NULL,
    `phase_1_completed_at`             DATETIME      DEFAULT NULL,
    `checkpoint_pre_assessment`        TINYINT(1)    NOT NULL DEFAULT 0,
    `checkpoint_pre_assessment_at`     DATETIME      DEFAULT NULL,
    `checkpoint_client_meetup`         TINYINT(1)    NOT NULL DEFAULT 0,
    `checkpoint_client_meetup_at`      DATETIME      DEFAULT NULL,
    `checkpoint_build_proposal`        TINYINT(1)    NOT NULL DEFAULT 0,
    `checkpoint_build_proposal_at`     DATETIME      DEFAULT NULL,
    `checkpoint_final_bid`             TINYINT(1)    NOT NULL DEFAULT 0,
    `checkpoint_final_bid_at`          DATETIME      DEFAULT NULL,
    `last_updated_by_user_id`          INT UNSIGNED  DEFAULT NULL,
    `is_deleted`                       TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`                       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assessor_id`) REFERENCES `users` (`id`),
    FOREIGN KEY (`last_updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`tier_id`)    REFERENCES `tiers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Assessment Answers Table
CREATE TABLE `assessment_answers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `answer_value` TEXT, -- Scalar value or JSON for multi-select
    `na_justification` TEXT DEFAULT NULL,
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `points_possible` DECIMAL(5,2) NOT NULL DEFAULT 2.00,
    `status_light` ENUM('GREEN', 'AMBER', 'RED') NOT NULL DEFAULT 'GREEN',
    `trigger_fired` ENUM('NONE', 'HOLD', 'STOP', 'ESCALATE') NOT NULL DEFAULT 'NONE',
    `is_applicable` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_assessment_question` (`assessment_id`, `question_id`),
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Explain Blocks Table
CREATE TABLE `explain_blocks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `responsible_party` ENUM('CLIENT', 'LENDER', 'ARCHITECT', 'ENGINEER', 'EXPEDITOR', 'UFC', 'OTHER') NOT NULL,
    `target_cure_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_assessment_question_explain` (`assessment_id`, `question_id`),
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Evidence Files Table
CREATE TABLE `evidence_files` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `explain_block_id` INT UNSIGNED DEFAULT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `uploaded_by_user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`explain_block_id`) REFERENCES `explain_blocks` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Phase Results Table
CREATE TABLE `phase_results` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `phase_id` INT UNSIGNED NOT NULL,
    `status` ENUM('LOCKED', 'IN_PROGRESS', 'PASS', 'FAIL_HOLD', 'FAIL_STOP', 'ESCALATED') NOT NULL DEFAULT 'LOCKED',
    `score_earned` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `score_possible` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `score_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `red_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `amber_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `stop_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `escalate_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `assessor_name` VARCHAR(100) DEFAULT NULL,
    `evaluated_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uniq_assessment_phase` (`assessment_id`, `phase_id`),
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. CEO Overrides Table
CREATE TABLE `ceo_overrides` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `phase_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED DEFAULT NULL,
    `trigger_type` ENUM('STOP', 'ESCALATE', 'PHASE_GATE') NOT NULL,
    `action` ENUM('OVERRIDE_TO_PASS', 'CLEAR_ESCALATION') NOT NULL,
    `justification` TEXT NOT NULL,
    `ceo_user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`phase_id`) REFERENCES `phases` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`ceo_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Follow-Up Tasks Table
CREATE TABLE `follow_up_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `explain_block_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `responsible_party` ENUM('CLIENT', 'LENDER', 'ARCHITECT', 'ENGINEER', 'EXPEDITOR', 'UFC', 'OTHER') NOT NULL,
    `target_cure_date` DATE NOT NULL,
    `status` ENUM('OPEN', 'RESOLVED', 'EXPIRED') NOT NULL DEFAULT 'OPEN',
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`explain_block_id`) REFERENCES `explain_blocks` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Assessment History / Audit Table
CREATE TABLE `assessment_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT, -- JSON format details
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Email Communication & Audit Logs Table
CREATE TABLE `email_logs` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id`   INT UNSIGNED NULL DEFAULT NULL,
    `recipient_email` VARCHAR(191) NOT NULL,
    `subject`         VARCHAR(255) NOT NULL,
    `email_type`      VARCHAR(50)  NOT NULL DEFAULT 'GENERAL',
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'SENT',
    `error_message`   TEXT         NULL DEFAULT NULL,
    `sent_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (`assessment_id`),
    INDEX (`recipient_email`),
    INDEX (`email_type`),
    INDEX (`sent_at`),
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Assessment Lifecycle Tracker (14-Day Timer)
CREATE TABLE `assessment_trackers` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id`    INT UNSIGNED NOT NULL UNIQUE,
    `status`           ENUM('ASSESSMENT_COMPLETED','DISCARDED','REJECTED','NOT_FIT') NULL DEFAULT NULL,
    `timer_started_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `first_started_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timer_cycles`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `stopped_at`       DATETIME     NULL DEFAULT NULL,
    `created_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (`assessment_id`),
    INDEX (`status`),
    INDEX (`timer_started_at`),
    FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
