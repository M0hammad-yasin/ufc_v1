<?php
/**
 * United Five Construction — Assessment Tracker API
 * ─────────────────────────────────────────────────────────────────────────────
 * Manages the 14-day lifecycle tracker for assessments.
 *
 * Actions:
 *   start       → Create a new tracker row (fails if already exists)
 *   resume      → Restart the 14-day timer (increments cycle count, clears stopped_at)
 *   set_status  → Set tracker status (ASSESSMENT_COMPLETED | DISCARDED | REJECTED)
 *                 and stamps stopped_at = NOW()
 *
 * POST JSON body:
 *   { "action": "start|resume|set_status", "assessment_id": int, "status"?: string }
 *
 * Response JSON:
 *   { success, tracker_status, days_elapsed, days_remaining, is_active, is_expired, message? }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$input        = json_decode(file_get_contents('php://input'), true);
$action       = trim($input['action'] ?? '');
$assessmentId = isset($input['assessment_id']) ? (int)$input['assessment_id'] : 0;
$newStatus    = trim($input['status'] ?? '');

if (!$assessmentId || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
    exit;
}

$validActions  = ['start', 'resume', 'set_status'];
$validStatuses = ['ASSESSMENT_COMPLETED', 'DISCARDED', 'REJECTED', 'NOT_FIT'];

if (!in_array($action, $validActions, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
    exit;
}

if ($action === 'set_status' && !in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid tracker status value.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Verify assessment exists and belongs to this session's visible scope
    $chkStmt = $pdo->prepare("SELECT id FROM assessments WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL) LIMIT 1");
    $chkStmt->execute([$assessmentId]);
    if (!$chkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Assessment not found.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    switch ($action) {

        case 'start':
            // Refuse if tracker already exists
            $existing = $pdo->prepare("SELECT id FROM assessment_trackers WHERE assessment_id = ? LIMIT 1");
            $existing->execute([$assessmentId]);
            if ($existing->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Tracker already started for this assessment.']);
                exit;
            }
            $ins = $pdo->prepare("
                INSERT INTO assessment_trackers
                    (assessment_id, status, timer_started_at, first_started_at, timer_cycles, stopped_at)
                VALUES (?, NULL, ?, ?, 1, NULL)
            ");
            $ins->execute([$assessmentId, $now, $now]);
            logAudit($assessmentId, 'TRACKER_STARTED', ['started_at' => $now]);
            break;

        case 'resume':
            $existing = $pdo->prepare("SELECT * FROM assessment_trackers WHERE assessment_id = ? LIMIT 1");
            $existing->execute([$assessmentId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(['success' => false, 'error' => 'No tracker found. Start one first.']);
                exit;
            }
            $upd = $pdo->prepare("
                UPDATE assessment_trackers
                SET timer_started_at = ?,
                    stopped_at = NULL,
                    status = NULL,
                    timer_cycles = timer_cycles + 1
                WHERE assessment_id = ?
            ");
            $upd->execute([$now, $assessmentId]);
            logAudit($assessmentId, 'TRACKER_RESUMED', ['resumed_at' => $now, 'cycle' => (int)$row['timer_cycles'] + 1]);
            break;

        case 'set_status':
            $existing = $pdo->prepare("SELECT * FROM assessment_trackers WHERE assessment_id = ? LIMIT 1");
            $existing->execute([$assessmentId]);
            if (!$existing->fetch()) {
                echo json_encode(['success' => false, 'error' => 'No tracker found for this assessment.']);
                exit;
            }
            $upd = $pdo->prepare("
                UPDATE assessment_trackers
                SET status = ?, stopped_at = ?
                WHERE assessment_id = ?
            ");
            $upd->execute([$newStatus, $now, $assessmentId]);
            logAudit($assessmentId, 'TRACKER_STATUS_SET', ['status' => $newStatus, 'stopped_at' => $now]);
            break;
    }

    // Re-fetch updated tracker for response
    $tracker = getAssessmentTracker($assessmentId, $pdo);

    echo json_encode([
        'success'       => true,
        'tracker_status'=> $tracker['status'],
        'is_active'     => $tracker['is_active'],
        'is_expired'    => $tracker['is_expired'],
        'days_elapsed'  => $tracker['days_elapsed'],
        'days_remaining'=> $tracker['days_remaining'],
        'timer_started_at' => $tracker['timer_started_at'],
        'stopped_at'    => $tracker['stopped_at'],
    ]);

} catch (Exception $e) {
    error_log('update_tracker.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
