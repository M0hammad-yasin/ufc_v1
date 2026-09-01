<?php
/**
 * database/migrate.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Automated Database Migration Runner
 * Safe to execute multiple times (idempotent).
 *
 * Can be run via:
 * 1. CLI:    php database/migrate.php
 * 2. Web:    http://localhost/ufc_v1/database/migrate.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>UFC Database Migration</title>";
    echo "<style>body{background:#060f1e;color:#e2e8f0;font-family:sans-serif;padding:30px;} .success{color:#4ade80;} .info{color:#c9a84c;} pre{background:#0d1f3c;padding:15px;border-radius:8px;border:1px solid #1e3e68;}</style></head><body>";
    echo "<h2>UFC Database Migration Runner</h2><pre>";
}

function outputMsg(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        echo "[{$type}] {$msg}\n";
    } else {
        echo "<span class='{$type}'>[{$type}] {$msg}</span>\n";
    }
}

try {
    $pdo = getDbConnection();
    outputMsg("Connecting to database `" . DB_NAME . "` on " . DB_HOST . "...", "info");

    // 1. Check existing columns on `assessments`
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `assessments`");
    $existingCols = [];
    while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingCols[$col['Field']] = true;
    }

    $columnsToAdd = [
        'phase_1_completed_at'         => "DATETIME DEFAULT NULL AFTER `completed_at`",
        'checkpoint_pre_assessment'    => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `phase_1_completed_at`",
        'checkpoint_pre_assessment_at' => "DATETIME DEFAULT NULL AFTER `checkpoint_pre_assessment`",
        'checkpoint_client_meetup'     => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_pre_assessment_at`",
        'checkpoint_client_meetup_at'  => "DATETIME DEFAULT NULL AFTER `checkpoint_client_meetup`",
        'checkpoint_build_proposal'    => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_client_meetup_at`",
        'checkpoint_build_proposal_at' => "DATETIME DEFAULT NULL AFTER `checkpoint_build_proposal`",
        'checkpoint_final_bid'         => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_build_proposal_at`",
        'checkpoint_final_bid_at'      => "DATETIME DEFAULT NULL AFTER `checkpoint_final_bid`",
        'last_updated_by_user_id'      => "INT UNSIGNED DEFAULT NULL AFTER `checkpoint_final_bid_at`",
    ];

    $addedCount = 0;
    foreach ($columnsToAdd as $colName => $colDef) {
        if (!isset($existingCols[$colName])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `{$colName}` {$colDef}");
            outputMsg("Added column: {$colName}", "success");
            $addedCount++;
        } else {
            outputMsg("Column already exists: {$colName}", "info");
        }
    }

    // 2. Add Foreign Key for last_updated_by_user_id if not present
    try {
        $fkCheck = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'assessments' 
              AND CONSTRAINT_NAME = 'fk_assessments_last_updated_by'
        ");
        $fkCheck->execute();
        if ((int)$fkCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `assessments` ADD CONSTRAINT `fk_assessments_last_updated_by` FOREIGN KEY (`last_updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            outputMsg("Added foreign key: fk_assessments_last_updated_by", "success");
        }
    } catch (Exception $e) {
        outputMsg("Foreign key check notice: " . $e->getMessage(), "info");
    }

    // 3. Backfill phase_1_completed_at for existing records that completed Phase 1
    $backfillSql = "
        UPDATE `assessments` a
        JOIN `phase_results` pr ON a.id = pr.assessment_id AND pr.phase_id = 1 AND pr.status = 'PASS'
        SET 
            a.phase_1_completed_at = COALESCE(a.phase_1_completed_at, pr.evaluated_at, a.updated_at, a.created_at),
            a.checkpoint_pre_assessment = 1,
            a.checkpoint_pre_assessment_at = COALESCE(a.checkpoint_pre_assessment_at, pr.evaluated_at, a.updated_at, a.created_at)
        WHERE a.phase_1_completed_at IS NULL
    ";
    // 4. Check and update `phases` table columns (weight, threshold)
    $phaseColsStmt = $pdo->query("SHOW COLUMNS FROM `phases`");
    $existingPhaseCols = [];
    while ($col = $phaseColsStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingPhaseCols[$col['Field']] = true;
    }

    if (!isset($existingPhaseCols['weight'])) {
        $pdo->exec("ALTER TABLE `phases` ADD COLUMN `weight` DECIMAL(5,3) NOT NULL DEFAULT 0.000 AFTER `question_count`");
        outputMsg("Added column: phases.weight", "success");
    }
    if (!isset($existingPhaseCols['threshold'])) {
        $pdo->exec("ALTER TABLE `phases` ADD COLUMN `threshold` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `weight`");
        outputMsg("Added column: phases.threshold", "success");
    }

    // Populate standard weights & thresholds for all 4 phases (100% based)
    $phaseConfig = [
        1 => ['weight' => 0.200, 'threshold' => 65.00],
        2 => ['weight' => 0.150, 'threshold' => 60.00],
        3 => ['weight' => 0.220, 'threshold' => 65.00],
        4 => ['weight' => 0.180, 'threshold' => 65.00],
    ];

    $stmtUpdPhase = $pdo->prepare("UPDATE `phases` SET `weight` = ?, `threshold` = ? WHERE `phase_number` = ?");
    foreach ($phaseConfig as $pNum => $cfg) {
        $stmtUpdPhase->execute([$cfg['weight'], $cfg['threshold'], $pNum]);
        outputMsg("Updated Phase {$pNum}: weight={$cfg['weight']}, threshold={$cfg['threshold']}", "info");
    }

    outputMsg("Database migration completed successfully! All tables, columns, and phase metrics are up to date.", "success");

} catch (Exception $e) {
    outputMsg("Migration Error: " . $e->getMessage(), "info");
}

if (!$isCli) {
    echo "</pre><p><a href='/ufc_v1/admin/assessments.php' style='color:#c9a84c;font-weight:bold;'>&larr; Return to Assessments Dashboard</a></p></body></html>";
}
