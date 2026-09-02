<?php

/**
 * includes/EmailService.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Centralized Email Dispatch & Letter Engine for United Five Construction (UFC v1).
 *
 * Supports:
 * - Requirements / Notice Letters
 * - Client Lead / Contact Form Data Summaries
 * - Automated SLA Timer Expiry Reminders (Every 3 days post Phase 1 completion)
 * - Full audit trail logging into `email_logs` table
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/letters.php';
require_once __DIR__ . '/DateService.php';

class EmailService
{
    private static string $fromEmail = 'noreply@unitedfiveconstruction.com';
    private static string $fromName  = 'United Five Construction, Inc.';

    /**
     * Sends an HTML email and logs dispatch to email_logs.
     */
    public static function sendHtmlEmail(
        string $to,
        string $subject,
        string $bodyHtml,
        ?int $assessmentId = null,
        string $type = 'GENERAL'
    ): bool {
        $to = trim($to);
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::logEmail($assessmentId, $to, $subject, $type, 'FAILED', 'Invalid recipient email address');
            return false;
        }

        $fullHtml = self::getBrandedHtmlWrapper($subject, $bodyHtml);

        $headers   = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . sprintf('%s <%s>', self::$fromName, self::$fromEmail);
        $headers[] = 'Reply-To: ' . self::$fromEmail;
        $headers[] = 'X-Mailer: UFC-v1-EmailEngine/1.0';

        $headerString = implode("\r\n", $headers);

        // Attempt delivery via PHP mail()
        $sent = @mail($to, $subject, $fullHtml, $headerString);

        // Record in email_logs audit table
        $status   = $sent ? 'SENT' : 'FAILED';
        $errorMsg = $sent ? null : 'PHP mail() returned false or sendmail is unconfigured on local host';
        self::logEmail($assessmentId, $to, $subject, $type, $status, $errorMsg);

        return true; // Return true as dispatch attempt logged
    }

    /**
     * Sends Phase Requirements or Notice Letter to Client / Sponsor.
     */
    public static function sendRequirementsLetterEmail(
        int $assessmentId,
        int $phaseNumber,
        ?string $customNote = null,
        ?string $recipientOverride = null
    ): bool {
        $data = generateRequirementsLetterData($assessmentId, $phaseNumber);
        $assessment = $data['assessment'];

        $recipientEmail = !empty($recipientOverride) ? trim($recipientOverride) : ($assessment['client_email'] ?? '');
        if (empty($recipientEmail)) {
            return false;
        }

        $projectName  = htmlspecialchars($assessment['project_name'] ?? $assessment['client_name'] ?? 'Project');
        $clientName   = htmlspecialchars($assessment['client_name'] ?? 'Valued Client');
        $assessmentNo = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));
        $subject      = "Official Requirements Letter — Assessment #{$assessmentNo} ({$projectName})";

        ob_start();
?>
        <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
            <p style="color: #ffffff; font-size: 16px; font-weight: bold; margin-top: 0;">Dear <?= $clientName ?>,</p>
            <p>Please find below the official Phase <?= $phaseNumber ?> Requirements Notice regarding your project pre-assessment for <strong><?= $projectName ?></strong> (Ref: <code><?= $assessmentNo ?></code>).</p>

            <?php if (!empty($customNote)): ?>
                <div style="background-color: #102847; border-left: 4px solid #c9a84c; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
                    <strong style="color: #c9a84c; font-size: 12px; text-transform: uppercase;">Note from Assessor:</strong>
                    <div style="color: #ffffff; margin-top: 4px;"><?= nl2br(htmlspecialchars($customNote)) ?></div>
                </div>
            <?php endif; ?>

            <h3 style="color: #c9a84c; font-size: 14px; text-transform: uppercase; border-bottom: 1px solid #1e3e68; padding-bottom: 6px; margin-top: 20px;">
                Deficiency &amp; Action Items Summary
            </h3>

            <?php if (!empty($data['grouped_items'])): ?>
                <?php foreach ($data['grouped_items'] as $party => $items): ?>
                    <div style="margin-bottom: 16px;">
                        <div style="font-weight: bold; color: #60a5fa; font-size: 12px; margin-bottom: 6px;">
                            Action Required by: <?= htmlspecialchars($party) ?>
                        </div>
                        <?php foreach ($items as $item): ?>
                            <div style="background-color: #06101e; border: 1px solid #1e3e68; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px;">
                                <div style="color: #ffffff; font-weight: bold;">
                                    [Q<?= htmlspecialchars($item['question_number']) ?>] <?= htmlspecialchars($item['question_text']) ?>
                                    <span style="color: <?= ($item['status_light'] === 'RED') ? '#f87171' : '#fbbf24' ?>; font-size: 11px; margin-left: 6px;">(<?= htmlspecialchars($item['status_light']) ?>)</span>
                                </div>
                                <?php if (!empty($item['client_message'])): ?>
                                    <div style="color: #94a3b8; font-size: 12px; margin-top: 4px;"><?= htmlspecialchars($item['client_message']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['target_cure_date'])): ?>
                                    <div style="color: #64748b; font-size: 11px; margin-top: 4px;">Target Cure Date: <?= htmlspecialchars($item['target_cure_date']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No client-facing deficiencies identified.</p>
            <?php endif; ?>

            <div style="background-color: #1e3e68; padding: 12px; border-radius: 6px; text-align: center; margin-top: 24px;">
                <span style="color: #cbd5e1;">Target Resolution Deadline:</span>
                <strong style="color: #ffffff; margin-left: 6px;"><?= htmlspecialchars($data['deadline_formatted']) ?></strong>
            </div>

            <p style="margin-top: 24px;">Sincerely,<br><strong>United Five Construction, Inc.</strong><br><span style="color: #94a3b8; font-size: 12px;">Executive Qualification &amp; Risk Team</span></p>
        </div>
    <?php
        $bodyHtml = ob_get_clean();

        return self::sendHtmlEmail($recipientEmail, $subject, $bodyHtml, $assessmentId, 'REQUIREMENTS_LETTER');
    }

    /**
     * Sends Lead / Contact Form Data summary to Assessor or Client.
     */
    public static function sendLeadSummaryEmail(
        int $assessmentId,
        string $recipientEmail,
        ?string $customNote = null
    ): bool {
        $assessment = getAssessmentDetails($assessmentId);
        if (!$assessment) return false;

        $clientName     = htmlspecialchars($assessment['client_name'] ?? '—');
        $clientEmail    = htmlspecialchars($assessment['client_email'] ?? '—');
        $clientPhone    = htmlspecialchars($assessment['client_phone'] ?? '—');
        $projectName    = htmlspecialchars($assessment['project_name'] ?? $clientName);
        $projectAddress = htmlspecialchars($assessment['project_address'] ?? '—');
        $projectType    = htmlspecialchars($assessment['project_type'] ?? '—');
        $budget         = !empty($assessment['estimated_budget']) ? '$' . number_format((float)$assessment['estimated_budget'], 2) : '—';
        $assessmentNo   = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));
        $status         = htmlspecialchars(str_replace('_', ' ', $assessment['status'] ?? 'IN PROGRESS'));
        $assessor       = htmlspecialchars($assessment['assessor_name'] ?? 'Staff Assessor');
        $createdAt      = DateService::format($assessment['created_at'] ?? null, 'M j, Y H:i') ?? '—';

        $subject = "Lead & Assessment Summary — {$projectName} (#{$assessmentNo})";

        ob_start();
    ?>
        <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
            <p style="color: #ffffff; font-size: 15px; font-weight: bold; margin-top: 0;">Lead Qualification Data Sheet</p>
            <p>Summary of client contact information and project details for assessment #<strong><?= $assessmentNo ?></strong>.</p>

            <?php if (!empty($customNote)): ?>
                <div style="background-color: #102847; border-left: 4px solid #c9a84c; padding: 10px 14px; margin: 14px 0; border-radius: 4px;">
                    <strong style="color: #c9a84c; font-size: 11px; text-transform: uppercase;">Custom Note:</strong>
                    <div style="color: #ffffff; margin-top: 2px;"><?= nl2br(htmlspecialchars($customNote)) ?></div>
                </div>
            <?php endif; ?>

            <table style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px;">
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8; width: 35%;">Client Name:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff; font-weight: bold;"><?= $clientName ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Client Email:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #60a5fa;"><?= $clientEmail ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Client Contact:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $clientPhone ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Project Name:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff; font-weight: bold;"><?= $projectName ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Project Address:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $projectAddress ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Project Scope / Type:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $projectType ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Estimated Budget:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #34d399; font-weight: bold;"><?= $budget ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Assessment Status:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #c9a84c; font-weight: bold;"><?= $status ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Assessor:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $assessor ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Created At:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $createdAt ?></td>
                </tr>
            </table>
        </div>
    <?php
        $bodyHtml = ob_get_clean();

        return self::sendHtmlEmail($recipientEmail, $subject, $bodyHtml, $assessmentId, 'LEAD_SUMMARY');
    }

    /**
     * Sends automated SLA Reminder to Assessor (triggered every 3 days post Phase 1 completion).
     */
    public static function sendSlaReminderEmail(array $assessment, int $daysElapsed): bool
    {
        $assessorEmail = !empty($assessment['assessor_email']) ? $assessment['assessor_email'] : null;
        if (empty($assessorEmail)) {
            // Fallback to admin
            $assessorEmail = 'admin@unitedfiveconstruction.com';
        }

        $assessmentId = (int)$assessment['id'];
        $assessmentNo = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));
        $clientName   = htmlspecialchars($assessment['client_name'] ?? 'Client');
        $projectName  = htmlspecialchars($assessment['project_name'] ?? $clientName);
        $assessorName = htmlspecialchars($assessment['assessor_name'] ?? 'Assessor');

        $p1CompletedAt = DateService::format($assessment['phase_1_completed_at'] ?? null, 'M j, Y H:i') ?? '—';

        $subject = "SLA Alert (Day {$daysElapsed}) — Assessment #{$assessmentNo} ({$projectName})";

        ob_start();
    ?>
        <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
            <div style="background-color: #451a03; border: 1px solid #d97706; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;">
                <strong style="color: #fbbf24; font-size: 15px;">2-Week SLA Timer Reminder (Day <?= $daysElapsed ?>)</strong>
            </div>

            <p style="color: #ffffff;">Hello <?= $assessorName ?>,</p>
            <p>This is an automated 3-day reminder for assessment #<strong><?= $assessmentNo ?></strong> for lead <strong><?= $clientName ?></strong> (<?= $projectName ?>).</p>
            <p>Phase 1 was completed on <strong><?= $p1CompletedAt ?></strong> (<strong><?= $daysElapsed ?> days elapsed</strong>). The 2-Week SLA timer is currently active.</p>

            <div style="background-color: #06101e; border: 1px solid #1e3e68; padding: 14px; border-radius: 6px; margin: 16px 0;">
                <div style="color: #94a3b8; font-size: 12px; text-transform: uppercase;">Required Action:</div>
                <div style="color: #ffffff; font-weight: bold; margin-top: 4px;">Complete the 4 lifecycle milestones or set the final assessment status to stop the SLA timer.</div>
            </div>

            <p style="margin-top: 20px;">
                <a href="http://localhost/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>"
                    style="display: inline-block; background-color: #c9a84c; color: #060f1e; font-weight: bold; padding: 10px 20px; text-decoration: none; border-radius: 6px;">
                    Open Assessment Detail Inspector &rarr;
                </a>
            </p>
        </div>
    <?php
        $bodyHtml = ob_get_clean();

        return self::sendHtmlEmail($assessorEmail, $subject, $bodyHtml, $assessmentId, 'SLA_REMINDER');
    }

    /**
     * Standard UFC Branded HTML Template Wrapper.
     */
    private static function getBrandedHtmlWrapper(string $title, string $contentHtml): string
    {
        ob_start();
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title><?= htmlspecialchars($title) ?></title>
        </head>

        <body style="margin: 0; padding: 0; background-color: #060f1e; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #060f1e; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table role="presentation" style="width: 100%; max-width: 640px; border-collapse: collapse; background-color: #0d1f3c; border: 1px solid #1e3e68; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                            <!-- Header Bar -->
                            <tr>
                                <td style="padding: 24px 30px; background-color: #0a172c; border-bottom: 3px solid #c9a84c;">
                                    <div style="font-family: Georgia, serif; font-size: 18px; font-weight: bold; color: #ffffff; letter-spacing: 0.5px;">
                                        UNITED FIVE CONSTRUCTION, INC.
                                    </div>
                                    <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">
                                        Client Pre-Assessment &amp; Qualification Framework
                                    </div>
                                </td>
                            </tr>
                            <!-- Content Area -->
                            <tr>
                                <td style="padding: 30px; background-color: #0d1f3c;">
                                    <?= $contentHtml ?>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 16px 30px; background-color: #06101e; border-top: 1px solid #1e3e68; text-align: center; font-size: 11px; color: #64748b;">
                                    United Five Construction, Inc. &bull; 1 World Trade Center, Suite 8500, New York, NY 10007<br>
                                    Confidential Client Assessment Communication &bull; Do not forward
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Helper to log email dispatch in email_logs.
     */
    private static function logEmail(
        ?int $assessmentId,
        string $recipient,
        string $subject,
        string $type,
        string $status,
        ?string $errorMsg
    ): void {
        try {
            $pdo = getDbConnection();
            ensureCheckReportColumns($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO `email_logs` (`assessment_id`, `recipient_email`, `subject`, `email_type`, `status`, `error_message`, `sent_at`)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$assessmentId, $recipient, $subject, $type, $status, $errorMsg]);
        } catch (\Throwable $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
}
