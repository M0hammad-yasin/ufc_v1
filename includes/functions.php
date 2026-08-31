<?php
/**
 * United Five Construction - Reusable Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

function sanitize(?string $str): string {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function formatDate(?string $dateStr, string $format = 'M j, Y'): string {
    if (!$dateStr) return 'N/A';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$dateStr;
    }
}

function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function logAudit(int $assessmentId, string $action, array $details = [], ?int $userId = null): void {
    try {
        $pdo = getDbConnection();
        if ($userId === null && isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }
        $stmt = $pdo->prepare("INSERT INTO `assessment_history` (`assessment_id`, `user_id`, `action`, `details`) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $assessmentId,
            $userId,
            $action,
            json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function ensureCheckReportColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM `assessments`");
        $existingCols = [];
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingCols[$col['Field']] = true;
        }

        if (!isset($existingCols['phase_1_completed_at'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `phase_1_completed_at` DATETIME NULL DEFAULT NULL AFTER `completed_at`");
        }
        if (!isset($existingCols['checkpoint_pre_assessment'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_pre_assessment` TINYINT(1) NOT NULL DEFAULT 0 AFTER `phase_1_completed_at`");
        }
        if (!isset($existingCols['checkpoint_pre_assessment_at'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_pre_assessment_at` DATETIME NULL DEFAULT NULL AFTER `checkpoint_pre_assessment`");
        }
        if (!isset($existingCols['checkpoint_client_meetup'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_client_meetup` TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_pre_assessment_at`");
        }
        if (!isset($existingCols['checkpoint_client_meetup_at'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_client_meetup_at` DATETIME NULL DEFAULT NULL AFTER `checkpoint_client_meetup`");
        }
        if (!isset($existingCols['checkpoint_build_proposal'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_build_proposal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_client_meetup_at`");
        }
        if (!isset($existingCols['checkpoint_build_proposal_at'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_build_proposal_at` DATETIME NULL DEFAULT NULL AFTER `checkpoint_build_proposal`");
        }
        if (!isset($existingCols['checkpoint_final_bid'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_final_bid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `checkpoint_build_proposal_at`");
        }
        if (!isset($existingCols['checkpoint_final_bid_at'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `checkpoint_final_bid_at` DATETIME NULL DEFAULT NULL AFTER `checkpoint_final_bid`");
        }
        if (!isset($existingCols['last_updated_by_user_id'])) {
            $pdo->exec("ALTER TABLE `assessments` ADD COLUMN `last_updated_by_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `checkpoint_final_bid_at`");
        }
    } catch (Exception $e) {
        error_log("Column check/migration failed: " . $e->getMessage());
    }
}

function generateAssessmentNumber(): string {
    return 'UFC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function getAssessmentDetails(int $assessmentId): ?array {
    $pdo = getDbConnection();
    ensureCheckReportColumns($pdo);
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            u_created.name AS assessor_name,
            u_created.email AS assessor_email,
            u_updated.name AS last_updated_by_name,
            u_updated.email AS last_updated_by_email
        FROM assessments a
        LEFT JOIN users u_created ON a.assessor_id = u_created.id
        LEFT JOIN users u_updated ON a.last_updated_by_user_id = u_updated.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$assessmentId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Computes Phase 1 2-Week SLA status and returns alert level & visual indicators.
 *
 * Rules:
 * - If Phase 1 is not yet passed: alert_level = 'NONE'
 * - If Proposal Submission or Final Bid milestone is checked: alert_level = 'COMPLETED'
 * - Within Week 1 (Days 1 to 7 since Phase 1 pass): alert_level = 'YELLOW' (Yellow blinking notification)
 * - Week 2 & Beyond (Days 8 to 14+ since Phase 1 pass): alert_level = 'RED' (Red blinking urgent notification)
 */
function getAssessmentSlaStatus(array $assessment): array {
    $phase1CompletedAt = $assessment['phase_1_completed_at'] ?? null;
    $proposalCompleted = (bool)($assessment['checkpoint_build_proposal'] ?? 0) || (bool)($assessment['checkpoint_final_bid'] ?? 0);
    $status = $assessment['status'] ?? 'IN_PROGRESS';

    // Check if Phase 1 was completed but phase_1_completed_at wasn't stamped yet
    if (!$phase1CompletedAt && isset($assessment['current_phase']) && (int)$assessment['current_phase'] > 1) {
        $phase1CompletedAt = $assessment['updated_at'] ?? $assessment['created_at'] ?? date('Y-m-d H:i:s');
    }

    if (!$phase1CompletedAt || $status === 'NOT_A_FIT') {
        return [
            'is_active'       => false,
            'alert_level'     => 'NONE',
            'days_elapsed'    => 0,
            'days_remaining'  => 14,
            'label'           => 'Phase 1 Pending',
            'badge_html'      => '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">Pre-P1</span>',
            'dot_html'        => '',
        ];
    }

    if ($proposalCompleted || $status === 'PROCEED_TO_PROPOSAL') {
        return [
            'is_active'       => false,
            'alert_level'     => 'COMPLETED',
            'days_elapsed'    => 0,
            'days_remaining'  => 0,
            'label'           => 'Proposal Built / Met',
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold bg-emerald-950/90 text-emerald-300 border border-emerald-500 shadow-sm"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Proposal Ready</span>',
            'dot_html'        => '<span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]" title="Proposal Milestone Completed"></span>',
        ];
    }

    try {
        $p1Date = new DateTime($phase1CompletedAt);
        $now = new DateTime();
        $diff = $p1Date->diff($now);
        $daysElapsed = (int)$diff->days;
        $daysRemaining = max(0, 14 - $daysElapsed);
    } catch (Exception $e) {
        $daysElapsed = 0;
        $daysRemaining = 14;
    }

    if ($daysElapsed <= 7) {
        // Week 1 (Days 1 to 7): Yellow blinking alert
        $dayLabel = ($daysElapsed === 0) ? 'Today' : "Day {$daysElapsed}";
        return [
            'is_active'       => true,
            'alert_level'     => 'YELLOW',
            'days_elapsed'    => $daysElapsed,
            'days_remaining'  => $daysRemaining,
            'label'           => "Week 1 ({$dayLabel}) · {$daysRemaining}d left",
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold bg-amber-950/90 text-amber-300 border border-amber-500/80 shadow-md animate-pulse"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span></span> Week 1 (' . $daysRemaining . 'd left)</span>',
            'dot_html'        => '<span class="relative inline-flex h-3 w-3 align-middle mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.9)]" title="Phase 1 Active (Week 1)"></span></span>',
        ];
    } else {
        // Week 2 and beyond (Days 8 to 14+): Red blinking urgent alert
        $overdueText = ($daysElapsed > 14) ? ' (' . ($daysElapsed - 14) . 'd overdue)' : " ({$daysRemaining}d left)";
        return [
            'is_active'       => true,
            'alert_level'     => 'RED',
            'days_elapsed'    => $daysElapsed,
            'days_remaining'  => $daysRemaining,
            'label'           => "Week 2 Escalation{$overdueText}",
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold bg-red-950/95 text-red-200 border border-red-500 shadow-md animate-pulse"><span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-90"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span></span> Week 2 Urgent' . $overdueText . '</span>',
            'dot_html'        => '<span class="relative inline-flex h-3 w-3 align-middle mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-90"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-600 shadow-[0_0_10px_rgba(239,68,68,1)]" title="Phase 1 Urgent Escalation (Week 2)"></span></span>',
        ];
    }
}

