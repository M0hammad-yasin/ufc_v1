<?php
/**
 * api/send_email.php
 * ─────────────────────────────────────────────────────────────────────────────
 * AJAX endpoint for sending emails from the UI:
 * - Requirements Letter (Notice/Decline Letter)
 * - Lead Summary / Contact Form Data
 * - Custom Email
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EmailService.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorised']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? $_POST;

$assessmentId = isset($body['assessment_id']) ? (int)$body['assessment_id'] : 0;
$action       = trim((string)($body['action'] ?? ''));
$recipient    = trim((string)($body['recipient_email'] ?? ''));
$phaseNumber  = isset($body['phase_number']) ? (int)$body['phase_number'] : 1;
$customNote   = trim((string)($body['custom_note'] ?? ''));

if ($assessmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid assessment ID']);
    exit;
}

$assessment = getAssessmentDetails($assessmentId);
if (!$assessment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Assessment not found']);
    exit;
}

try {
    if ($action === 'send_letter') {
        $targetEmail = !empty($recipient) ? $recipient : ($assessment['client_email'] ?? '');
        $ok = EmailService::sendRequirementsLetterEmail($assessmentId, $phaseNumber, $customNote, $targetEmail);
        
        logAudit($assessmentId, 'EMAIL_SENT', [
            'email_type' => 'REQUIREMENTS_LETTER',
            'recipient' => $targetEmail,
            'sent_by' => $currentUser['name']
        ], (int)$currentUser['id']);

        echo json_encode(['success' => true, 'message' => "Requirements letter sent to {$targetEmail}"]);
        exit;
    } elseif ($action === 'send_lead_summary') {
        $targetEmail = !empty($recipient) ? $recipient : ($assessment['client_email'] ?? '');
        $ok = EmailService::sendLeadSummaryEmail($assessmentId, $targetEmail, $customNote);

        logAudit($assessmentId, 'EMAIL_SENT', [
            'email_type' => 'LEAD_SUMMARY',
            'recipient' => $targetEmail,
            'sent_by' => $currentUser['name']
        ], (int)$currentUser['id']);

        echo json_encode(['success' => true, 'message' => "Lead summary sent to {$targetEmail}"]);
        exit;
    } elseif ($action === 'send_custom') {
        $subject = trim((string)($body['subject'] ?? 'Notification from United Five Construction'));
        $message = trim((string)($body['message'] ?? ''));

        if (empty($recipient) || empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Recipient and message content are required']);
            exit;
        }

        $bodyHtml = '<div>' . nl2br(htmlspecialchars($message)) . '</div>';
        $ok = EmailService::sendHtmlEmail($recipient, $subject, $bodyHtml, $assessmentId, 'CUSTOM');

        logAudit($assessmentId, 'EMAIL_SENT', [
            'email_type' => 'CUSTOM',
            'recipient' => $recipient,
            'sent_by' => $currentUser['name']
        ], (int)$currentUser['id']);

        echo json_encode(['success' => true, 'message' => "Email sent to {$recipient}"]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()]);
    exit;
}
