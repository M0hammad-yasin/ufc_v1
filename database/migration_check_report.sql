-- ─────────────────────────────────────────────────────────────────────────────
-- United Five Construction, Inc. (UFC Pre-Assessment System)
-- Database Migration: Check Report Milestones & 2-Week SLA Tracker
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. Add Phase 1 SLA & Milestone tracking columns to `assessments` table
ALTER TABLE `assessments`
    ADD COLUMN IF NOT EXISTS `phase_1_completed_at`             DATETIME      DEFAULT NULL AFTER `completed_at`,
    ADD COLUMN IF NOT EXISTS `checkpoint_pre_assessment`        TINYINT(1)    NOT NULL DEFAULT 0 AFTER `phase_1_completed_at`,
    ADD COLUMN IF NOT EXISTS `checkpoint_pre_assessment_at`     DATETIME      DEFAULT NULL AFTER `checkpoint_pre_assessment`,
    ADD COLUMN IF NOT EXISTS `checkpoint_client_meetup`         TINYINT(1)    NOT NULL DEFAULT 0 AFTER `checkpoint_pre_assessment_at`,
    ADD COLUMN IF NOT EXISTS `checkpoint_client_meetup_at`      DATETIME      DEFAULT NULL AFTER `checkpoint_client_meetup`,
    ADD COLUMN IF NOT EXISTS `checkpoint_build_proposal`        TINYINT(1)    NOT NULL DEFAULT 0 AFTER `checkpoint_client_meetup_at`,
    ADD COLUMN IF NOT EXISTS `checkpoint_build_proposal_at`     DATETIME      DEFAULT NULL AFTER `checkpoint_build_proposal`,
    ADD COLUMN IF NOT EXISTS `last_updated_by_user_id`          INT UNSIGNED  DEFAULT NULL AFTER `checkpoint_build_proposal_at`;

-- 2. Add Foreign Key for last_updated_by_user_id (if not already present)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.table_constraints 
    WHERE table_schema = DATABASE() 
      AND table_name = 'assessments' 
      AND constraint_name = 'fk_assessments_last_updated_by'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `assessments` ADD CONSTRAINT `fk_assessments_last_updated_by` FOREIGN KEY (`last_updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT "Foreign key fk_assessments_last_updated_by already exists" AS msg'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Backfill `phase_1_completed_at` for existing assessments that have passed Phase 1
UPDATE `assessments` a
JOIN `phase_results` pr ON a.id = pr.assessment_id AND pr.phase_id = 1 AND pr.status = 'PASS'
SET 
    a.phase_1_completed_at = COALESCE(a.phase_1_completed_at, pr.evaluated_at, a.updated_at, a.created_at),
    a.checkpoint_pre_assessment = 1,
    a.checkpoint_pre_assessment_at = COALESCE(a.checkpoint_pre_assessment_at, pr.evaluated_at, a.updated_at, a.created_at)
WHERE a.phase_1_completed_at IS NULL;
