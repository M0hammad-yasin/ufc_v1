<?php
/**
 * United Five Construction - 30-Day Response Window Automated Expiration
 * Run via CLI or cron job: php cron/check_deadlines.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Running 30-Day Deadline Expiration Check...\n";

try {
    $pdo = getDbConnection();

    // Select all assessments on HOLD where hold_deadline_date is in the past
    $stmt = $pdo->prepare("
        SELECT id, assessment_number, client_name, project_address, current_phase, hold_deadline_date 
        FROM assessments 
        WHERE status = 'HOLD' 
          AND hold_deadline_date IS NOT NULL 
          AND hold_deadline_date < CURRENT_DATE()
    ");
    $stmt->execute();
    $overdueAssessments = $stmt->fetchAll();

    $expiredCount = 0;
    foreach ($overdueAssessments as $ass) {
        $assessmentId = (int)$ass['id'];

        // Mark assessment as NOT A FIT due to UNRESPONSIVE
        $updateStmt = $pdo->prepare("
            UPDATE assessments 
            SET status = 'NOT_A_FIT', 
                decline_reason = 'UNRESPONSIVE', 
                decline_notes = '30-day response window expired with no client submission.',
                completed_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$assessmentId]);

        // Mark associated open tasks as EXPIRED
        $taskStmt = $pdo->prepare("
            UPDATE follow_up_tasks 
            SET status = 'EXPIRED' 
            WHERE assessment_id = ? AND status = 'OPEN'
        ");
        $taskStmt->execute([$assessmentId]);

        // Log audit
        logAudit($assessmentId, 'AUTO_EXPIRED_UNRESPONSIVE', [
            'deadline' => $ass['hold_deadline_date'],
            'phase' => $ass['current_phase']
        ]);

        echo "-> Auto-expired Assessment #{$ass['assessment_number']} ({$ass['client_name']}) - Phase {$ass['current_phase']}\n";
        $expiredCount++;
    }

    echo "Completed. Total assessments auto-expired: {$expiredCount}.\n";

} catch (Exception $e) {
    echo "ERROR during deadline check: " . $e->getMessage() . "\n";
}
