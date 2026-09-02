<?php
/**
 * cron/sla_reminders.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Automated SLA Reminder Task for United Five Construction (UFC v1).
 *
 * Rules:
 * - Monitors assessments where `phase_1_completed_at` IS NOT NULL.
 * - Runs for active assessments where SLA timer is still active (all 4 checkboxes not checked & no final status).
 * - Dispatches an automated SLA reminder email every 3 days post Phase 1 completion (Day 3, Day 6, Day 9, etc.).
 * - Uses `email_logs` table to ensure duplicate emails are NOT sent on the same day.
 *
 * Usage:
 * - CLI:  php cron/sla_reminders.php
 * - Web:  GET /ufc_v1/cron/sla_reminders.php?key=UFC_CRON_SECRET
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Web security check if invoked via browser
if (php_sapi_name() !== 'cli') {
    $cronKey = $_GET['key'] ?? '';
    if ($cronKey !== 'UFC_CRON_SECRET') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = getDbConnection();
ensureCheckReportColumns($pdo);

// Fetch assessments where phase_1_completed_at is not null
$stmt = $pdo->query("
    SELECT a.*,
           u_created.name AS assessor_name,
           u_created.email AS assessor_email
    FROM assessments a
    LEFT JOIN users u_created ON a.assessor_id = u_created.id
    WHERE a.phase_1_completed_at IS NOT NULL
      AND a.status NOT IN ('PROCEED_TO_PROPOSAL', 'HOLD', 'NOT_A_FIT', 'ABORTED')
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$processedCount = 0;
$sentCount      = 0;

foreach ($assessments as $ass) {
    $processedCount++;
    $assessmentId = (int)$ass['id'];

    // Check if all 4 milestone checkboxes are checked (if so, timer is stopped)
    $chk1 = (bool)($ass['checkpoint_pre_assessment'] ?? 0);
    $chk2 = (bool)($ass['checkpoint_client_meetup'] ?? 0);
    $chk3 = (bool)($ass['checkpoint_build_proposal'] ?? 0);
    $chk4 = (bool)($ass['checkpoint_final_bid'] ?? 0);
    if ($chk1 && $chk2 && $chk3 && $chk4) {
        continue; // Timer is completed
    }

    $p1Time = strtotime($ass['phase_1_completed_at']);
    if (!$p1Time) continue;

    $nowTime = time();
    $daysElapsed = (int)floor(($nowTime - $p1Time) / 86400);

    // Auto send email after every 3 days (e.g. Day 3, 6, 9, 12, 15...)
    if ($daysElapsed > 0 && ($daysElapsed % 3 === 0)) {
        // Check if an SLA reminder was already sent today for this assessment
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM `email_logs`
            WHERE `assessment_id` = ? 
              AND `email_type` = 'SLA_REMINDER'
              AND DATE(`sent_at`) = CURRENT_DATE()
        ");
        $checkStmt->execute([$assessmentId]);
        $alreadySentToday = (int)$checkStmt->fetchColumn() > 0;

        if (!$alreadySentToday) {
            $ok = EmailService::sendSlaReminderEmail($ass, $daysElapsed);
            if ($ok) {
                $sentCount++;
                logAudit($assessmentId, 'SLA_REMINDER_SENT', [
                    'days_elapsed' => $daysElapsed,
                    'triggered_by' => 'AUTOMATED_CRON'
                ]);
            }
        }
    }
}

$response = [
    'success'         => true,
    'timestamp'       => date('Y-m-d H:i:s'),
    'assessments_checked' => $processedCount,
    'emails_sent'     => $sentCount,
];

if (php_sapi_name() === 'cli') {
    echo "UFC SLA Reminder Cron Execution Complete.\n";
    echo "Checked: {$processedCount} | Reminders Sent: {$sentCount}\n";
} else {
    echo json_encode($response);
}
