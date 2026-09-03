<?php

/**
 * United Five Construction - Reusable Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

function sanitize(?string $str): string
{
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function formatDate(?string $dateStr, string $format = 'M j, Y'): string
{
    if (!$dateStr) return 'N/A';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$dateStr;
    }
}

function setFlashMessage(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessages(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function logAudit(int $assessmentId, string $action, array $details = [], ?int $userId = null): void
{
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

/**
 * Maintained as a lightweight no-op for backward compatibility.
 * All database tables, columns, and indexes are defined authoritatively in database/schema.sql.
 */
function ensureCheckReportColumns(?PDO $pdo = null): void
{
    // Schema is authoritative in database/schema.sql. No runtime inspection or alter needed.
}

function generateAssessmentNumber(): string
{
    return 'UFC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function getAssessmentDetails(int $assessmentId): ?array
{
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
 * Computes Assessment 14-Day Tracker SLA status using the `assessment_trackers` table.
 *
 * Rules:
 * - If tracker is not started (Phase 1 pending): alert_level = 'NONE' ("Phase 1 Pending")
 * - If tracker is stopped (status set or stopped_at set): alert_level = 'COMPLETED' (Timer Stopped with status badge)
 * - If tracker is active:
 *     - Week 1 (Days 0 to 7 since current timer start): alert_level = 'YELLOW' (Yellow blinking notification)
 *     - Week 2 & Beyond (Days 8 to 14+ since current timer start): alert_level = 'RED' (Red blinking urgent notification)
 */
function getAssessmentSlaStatus(array $assessment, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? getDbConnection();
    $assessmentId = (int)($assessment['id'] ?? 0);
    $tracker = ($assessmentId > 0) ? getAssessmentTracker($assessmentId, $pdo) : null;

    // If tracker not found, check if Phase 1 was already completed/passed; if so, auto-start tracker
    if (!$tracker && $assessmentId > 0) {
        $isP1Passed = !empty($assessment['phase_1_completed_at']) || ((int)($assessment['current_phase'] ?? 1) > 1);
        if (!$isP1Passed) {
            $p1Stmt = $pdo->prepare("SELECT COUNT(*) FROM phase_results WHERE assessment_id = ? AND phase_id = 1 AND status = 'PASS'");
            $p1Stmt->execute([$assessmentId]);
            $isP1Passed = ((int)$p1Stmt->fetchColumn()) > 0;
        }
        if ($isP1Passed) {
            startAssessmentTracker($assessmentId, $pdo);
            $tracker = getAssessmentTracker($assessmentId, $pdo);
        }
    }

    // 1. Tracker not started yet (Phase 1 still in progress or not passed)
    if (!$tracker) {
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

    // 2. Tracker stopped (status explicitly updated or stopped_at stamped)
    if (!$tracker['is_active']) {
        $tStatus = $tracker['status'] ?? 'STOPPED';
        $badgeText = match ($tStatus) {
            'ASSESSMENT_COMPLETED' => 'Assessment Completed',
            'DISCARDED'            => 'Assessment Discarded',
            'REJECTED', 'NOT_FIT'  => 'Assessment Rejected',
            default                => 'Timer Stopped',
        };
        $badgeClass = match ($tStatus) {
            'ASSESSMENT_COMPLETED' => 'bg-emerald-950/90 text-emerald-300 border-emerald-500',
            'DISCARDED'            => 'bg-amber-950/90 text-amber-300 border-amber-500',
            'REJECTED', 'NOT_FIT'  => 'bg-red-950/90 text-red-300 border-red-500',
            default                => 'bg-slate-800 text-slate-300 border-slate-600',
        };
        return [
            'is_active'       => false,
            'alert_level'     => 'COMPLETED',
            'days_elapsed'    => $tracker['days_elapsed'],
            'days_remaining'  => 0,
            'label'           => $badgeText . ' · Timer Stopped',
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold ' . $badgeClass . ' shadow-sm"><span class="w-2 h-2 rounded-full bg-current"></span> ' . $badgeText . ' (Timer Stopped)</span>',
            'dot_html'        => '<span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm" title="' . $badgeText . '"></span>',
        ];
    }

    // 3. Tracker is active (running 14-day window based on timer_started_at)
    $daysElapsed   = (int)$tracker['days_elapsed'];
    $daysRemaining = (int)$tracker['days_remaining'];

    if ($daysElapsed <= 7) {
        // Week 1 (Days 0 to 7): Yellow blinking notification
        $dayLabel = ($daysElapsed === 0) ? 'Today' : "Day {$daysElapsed}";
        return [
            'is_active'       => true,
            'alert_level'     => 'YELLOW',
            'days_elapsed'    => $daysElapsed,
            'days_remaining'  => $daysRemaining,
            'label'           => "Week 1 ({$dayLabel}) · {$daysRemaining}d left",
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold bg-amber-950/90 text-amber-300 border border-amber-500/80 shadow-md animate-pulse"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span></span> Week 1 (' . $daysRemaining . 'd left)</span>',
            'dot_html'        => '<span class="relative inline-flex h-3 w-3 align-middle mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.9)]" title="Timer Active (Week 1)"></span></span>',
        ];
    } else {
        // Week 2 and beyond (Days 8 to 14+): Red blinking urgent notification
        $overdueText = ($daysElapsed > 14) ? ' (' . ($daysElapsed - 14) . 'd overdue)' : " ({$daysRemaining}d left)";
        return [
            'is_active'       => true,
            'alert_level'     => 'RED',
            'days_elapsed'    => $daysElapsed,
            'days_remaining'  => $daysRemaining,
            'label'           => "Week 2 Escalation{$overdueText}",
            'badge_html'      => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-bold bg-red-950/95 text-red-200 border border-red-500 shadow-md animate-pulse"><span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-90"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span></span> Week 2 Urgent' . $overdueText . '</span>',
            'dot_html'        => '<span class="relative inline-flex h-3 w-3 align-middle mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-90"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-600 shadow-[0_0_10px_rgba(239,68,68,1)]" title="Timer Urgent Escalation (Week 2)"></span></span>',
        ];
    }
}

/**
 * Returns true if ALL 4 phases have been assessed (have any phase_result row).
 * Used to auto-check the first "Assessment" checkpoint (read-only).
 */
function allPhasesAssessed(int $assessmentId, PDO $pdo): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ph.phase_number)
        FROM phase_results pr
        JOIN phases ph ON pr.phase_id = ph.id
        WHERE pr.assessment_id = ?
        AND ph.phase_number IN (1, 2, 3, 4)
    ");
    $stmt->execute([$assessmentId]);
    return ((int)$stmt->fetchColumn()) >= 4;
}

/**
 * Returns true if ALL 4 phases have a phase_result with status = 'PASS'.
 * Used to unlock milestones 2-4 and conditionally show 'Assessment Completed' in tracker dropdown.
 */
function allPhasesPassed(int $assessmentId, PDO $pdo): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ph.phase_number)
        FROM phase_results pr
        JOIN phases ph ON pr.phase_id = ph.id
        WHERE pr.assessment_id = ?
        AND ph.phase_number IN (1, 2, 3, 4)
        AND pr.status = 'PASS'
    ");
    $stmt->execute([$assessmentId]);
    return ((int)$stmt->fetchColumn()) >= 4;
}

/**
 * Returns true if ANY phase_result for this assessment has status = 'HOLD'.
 * Used to conditionally show 'Send Requirements Letter' email button.
 */
function hasPhaseOnHold(int $assessmentId, PDO $pdo): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM phase_results
        WHERE assessment_id = ? AND status = 'FAIL_HOLD'
    ");
    $stmt->execute([$assessmentId]);
    return ((int)$stmt->fetchColumn()) > 0;
}

/**
 * Returns the assessment_trackers row for an assessment, or null if none started yet.
 * Includes computed fields: days_elapsed, days_remaining, is_active, is_expired.
 */
function getAssessmentTracker(int $assessmentId, PDO $pdo): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM assessment_trackers WHERE assessment_id = ? LIMIT 1");
    $stmt->execute([$assessmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $daysElapsed   = 0;
    $daysRemaining = 14;
    try {
        $started = new DateTime($row['timer_started_at']);
        $now     = new DateTime();
        $daysElapsed   = (int)$started->diff($now)->days;
        $daysRemaining = max(0, 14 - $daysElapsed);
    } catch (Exception $e) {
    }

    $row['is_active']      = ($row['stopped_at'] === null);
    $row['is_expired']     = ($row['stopped_at'] === null && $daysElapsed >= 14);
    $row['days_elapsed']   = $daysElapsed;
    $row['days_remaining'] = $daysRemaining;

    return $row;
}

/**
 * Starts the 14-day lifecycle tracker for an assessment when Phase 1 is completed.
 * Idempotent: Does nothing if a tracker already exists.
 */
function startAssessmentTracker(int $assessmentId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?? getDbConnection();
    $existing = $pdo->prepare("SELECT id FROM assessment_trackers WHERE assessment_id = ? LIMIT 1");
    $existing->execute([$assessmentId]);
    if (!$existing->fetch()) {
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("
            INSERT INTO assessment_trackers
                (assessment_id, status, timer_started_at, first_started_at, timer_cycles, stopped_at)
            VALUES (?, NULL, ?, ?, 1, NULL)
        ");
        $ins->execute([$assessmentId, $now, $now]);
        logAudit($assessmentId, 'TRACKER_AUTO_STARTED', ['started_at' => $now]);
    }
}
