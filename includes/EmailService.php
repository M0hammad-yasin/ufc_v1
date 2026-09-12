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
    /**
     * Gathers structured data for Lead Summary & Qualification Report.
     */
    public static function getLeadSummaryData(
        int $assessmentId,
        string|int $phaseChoice = 'all',
        bool $includeResults = true,
        bool $includeAnswers = true
    ): ?array {
        $pdo = getDbConnection();
        $assessment = getAssessmentDetails($assessmentId);
        if (!$assessment) {
            return null;
        }

        $phaseChoiceStr = (string)$phaseChoice;

        if ($phaseChoiceStr === 'all' || empty($phaseChoiceStr) || $phaseChoiceStr === '0') {
            // Fetch only phases that have at least one answered question for this assessment
            $stmtP = $pdo->prepare("
                SELECT DISTINCT p.*
                FROM phases p
                JOIN questions q ON q.phase_id = p.id
                JOIN assessment_answers aa ON aa.question_id = q.id
                WHERE aa.assessment_id = ?
                  AND aa.answer_value IS NOT NULL
                  AND aa.answer_value != ''
                ORDER BY p.phase_number ASC
            ");
            $stmtP->execute([$assessmentId]);
        } else {
            $phaseNum = max(1, (int)$phaseChoiceStr);
            $stmtP = $pdo->prepare("SELECT * FROM phases WHERE phase_number = ? LIMIT 1");
            $stmtP->execute([$phaseNum]);
        }
        $phases = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        $phaseNumbers = array_column($phases, 'phase_number');

        $phaseData = [];
        foreach ($phases as $p) {
            $pId = (int)$p['id'];

            $result = null;
            if ($includeResults) {
                $stmtR = $pdo->prepare("SELECT * FROM phase_results WHERE assessment_id = ? AND phase_id = ? LIMIT 1");
                $stmtR->execute([$assessmentId, $pId]);
                $result = $stmtR->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $questions = [];
            if ($includeAnswers) {
                $stmtQ = $pdo->prepare("
                    SELECT 
                        q.id,
                        q.question_number,
                        q.question_text,
                        q.client_message,
                        q.owner,
                        q.visibility,
                        q.order_index,
                        aa.answer_value,
                        aa.score,
                        aa.points_possible,
                        aa.status_light,
                        aa.trigger_fired,
                        eb.reason AS explain_reason,
                        eb.responsible_party,
                        eb.target_cure_date
                    FROM assessment_answers aa
                    JOIN questions q ON aa.question_id = q.id
                    LEFT JOIN explain_blocks eb ON (eb.question_id = q.id AND eb.assessment_id = aa.assessment_id)
                    WHERE aa.assessment_id = ?
                      AND q.phase_id = ?
                      AND aa.answer_value IS NOT NULL
                      AND aa.answer_value != ''
                    ORDER BY q.order_index ASC
                ");
                $stmtQ->execute([$assessmentId, $pId]);
                $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
            }

            $phaseData[] = [
                'phase' => $p,
                'result' => $result,
                'questions' => $questions
            ];
        }

        return [
            'assessment' => $assessment,
            'phase_choice' => $phaseChoiceStr,
            'phase_numbers' => $phaseNumbers,
            'include_results' => $includeResults,
            'include_answers' => $includeAnswers,
            'phase_data' => $phaseData
        ];
    }

    /**
     * Sends Lead / Contact Form Data summary to given address with chosen phase results & answers.
     */
    public static function sendLeadSummaryEmail(
        int $assessmentId,
        string $recipientEmail,
        ?string $customNote = null,
        string|int $phaseChoice = 'all',
        bool $includeResults = true,
        bool $includeAnswers = true
    ): bool {
        $data = self::getLeadSummaryData($assessmentId, $phaseChoice, $includeResults, $includeAnswers);
        if (!$data) return false;

        $assessment = $data['assessment'];
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

        $phaseLabel = ($data['phase_choice'] === 'all') 
            ? 'All Phases (1–4)' 
            : 'Phase ' . implode(', ', $data['phase_numbers']);

        $subject = "Lead & Qualification Summary — {$projectName} (#{$assessmentNo}) [{$phaseLabel}]";

        ob_start();
    ?>
        <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
            <div style="border-bottom: 2px solid #c9a84c; padding-bottom: 8px; margin-bottom: 16px;">
                <p style="color: #ffffff; font-size: 16px; font-weight: bold; margin: 0;">Lead Qualification Data &amp; Phase Report</p>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 3px;">
                    Scope: <strong style="color: #c9a84c;"><?= htmlspecialchars($phaseLabel) ?></strong> &middot; Assessment Ref: <code><?= $assessmentNo ?></code>
                </div>
            </div>

            <?php if (!empty($customNote)): ?>
                <div style="background-color: #102847; border-left: 4px solid #c9a84c; padding: 12px 14px; margin: 14px 0 18px 0; border-radius: 4px;">
                    <strong style="color: #c9a84c; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Message from Assessor:</strong>
                    <div style="color: #ffffff; margin-top: 4px; font-size: 13px;"><?= nl2br(htmlspecialchars($customNote)) ?></div>
                </div>
            <?php endif; ?>

            <!-- Client & Lead Overview -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px;">
                <tr>
                    <td colspan="2" style="padding: 8px 12px; background-color: #122849; border: 1px solid #1e3e68; color: #c9a84c; font-weight: bold; text-transform: uppercase; font-size: 11px;">
                        Lead &amp; Property Information
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8; width: 35%;">Client / Developer:</td>
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
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Scope / Type:</td>
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
                    <td style="padding: 8px 12px; background-color: #06101e; border: 1px solid #1e3e68; color: #94a3b8;">Intake Date:</td>
                    <td style="padding: 8px 12px; background-color: #0d1f3c; border: 1px solid #1e3e68; color: #ffffff;"><?= $createdAt ?></td>
                </tr>
            </table>

            <!-- Phase Results & Answers Section -->
            <?php foreach ($data['phase_data'] as $pBlock): 
                $p = $pBlock['phase'];
                $r = $pBlock['result'];
                $questions = $pBlock['questions'];
            ?>
                <div style="background-color: #06101e; border: 1px solid #1e3e68; border-radius: 8px; margin-bottom: 20px; overflow: hidden;">
                    <!-- Phase Title Banner -->
                    <div style="background-color: #122849; padding: 10px 14px; border-bottom: 1px solid #1e3e68; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold; color: #ffffff; font-size: 13px;">
                            Phase <?= $p['phase_number'] ?>: <?= htmlspecialchars($p['title']) ?>
                        </span>
                    </div>

                    <div style="padding: 14px;">
                        <!-- Results Summary -->
                        <?php if ($includeResults): ?>
                            <?php if ($r): 
                                $statusStyle = match($r['status']) {
                                    'PASS'      => 'background-color: #064e3b; color: #34d399; border: 1px solid #059669;',
                                    'FAIL_HOLD' => 'background-color: #451a03; color: #fbbf24; border: 1px solid #d97706;',
                                    'FAIL_STOP' => 'background-color: #450a0a; color: #f87171; border: 1px solid #dc2626;',
                                    default     => 'background-color: #172554; color: #60a5fa; border: 1px solid #2563eb;',
                                };
                            ?>
                                <div style="background-color: #0a172c; border: 1px solid #1e3e68; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                        <tr>
                                            <td style="color: #94a3b8; width: 35%;">Phase Gate Status:</td>
                                            <td>
                                                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; <?= $statusStyle ?>">
                                                    <?= htmlspecialchars($r['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="color: #94a3b8; padding-top: 4px;">Score Achieved:</td>
                                            <td style="color: #ffffff; padding-top: 4px; font-weight: bold;">
                                                <?= number_format((float)$r['score_earned'], 2) ?> / <?= number_format((float)$r['score_possible'], 2) ?> 
                                                (<?= number_format((float)$r['score_percent'], 1) ?>%)
                                            </td>
                                        </tr>
                                        <?php if ((int)$r['red_count'] > 0 || (int)$r['amber_count'] > 0): ?>
                                            <tr>
                                                <td style="color: #94a3b8; padding-top: 4px;">Deficiencies:</td>
                                                <td style="padding-top: 4px;">
                                                    <?php if ((int)$r['red_count'] > 0): ?>
                                                        <span style="color: #f87171; font-weight: bold; margin-right: 8px;">● <?= (int)$r['red_count'] ?> RED</span>
                                                    <?php endif; ?>
                                                    <?php if ((int)$r['amber_count'] > 0): ?>
                                                        <span style="color: #fbbf24; font-weight: bold;">● <?= (int)$r['amber_count'] ?> AMBER</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="color: #64748b; font-size: 11px; font-style: italic; margin-bottom: 10px;">
                                    Phase not yet evaluated or completed.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Itemized Questions & Answers (Only Answered Questions for this Assessment) -->
                        <?php if ($includeAnswers): ?>
                            <div style="margin-top: 10px;">
                                <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
                                    Answered Questions (<?= count($questions) ?>)
                                </div>

                                <?php if (!empty($questions)): ?>
                                    <?php foreach ($questions as $q): 
                                        $light = strtoupper($q['status_light'] ?? 'GREEN');
                                        $lightColor = match($light) {
                                            'RED'   => '#f87171',
                                            'AMBER' => '#fbbf24',
                                            default => '#34d399',
                                        };
                                        $lightBg = match($light) {
                                            'RED'   => 'background-color: #450a0a; border: 1px solid #991b1b;',
                                            'AMBER' => 'background-color: #451a03; border: 1px solid #92400e;',
                                            default => 'background-color: #064e3b; border: 1px solid #166534;',
                                        };
                                    ?>
                                        <div style="background-color: #0d1f3c; border: 1px solid #1e3e68; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; font-size: 12px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div style="color: #ffffff; font-weight: bold;">
                                                    <span style="color: #94a3b8; font-family: monospace;">[Q<?= htmlspecialchars($q['question_number']) ?>]</span>
                                                    <?= htmlspecialchars($q['question_text']) ?>
                                                </div>
                                                <span style="display: inline-block; padding: 1px 6px; border-radius: 3px; font-weight: bold; font-size: 10px; color: <?= $lightColor ?>; <?= $lightBg ?> margin-left: 8px;">
                                                    <?= $light ?>
                                                </span>
                                            </div>

                                            <!-- Recorded Answer -->
                                            <div style="margin-top: 5px; color: #cbd5e1; font-size: 11.5px;">
                                                <span style="color: #94a3b8;">Recorded Response:</span>
                                                <strong style="color: #ffffff;"><?= htmlspecialchars(formatAnswerValue($q['answer_value'] ?? null, $q)) ?></strong>
                                                <?php if ($q['score'] !== null): ?>
                                                    <span style="color: #64748b; margin-left: 6px;">(Score: <?= number_format((float)$q['score'], 2) ?> / <?= number_format((float)$q['points_possible'], 2) ?>)</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Guidance / Deficiency Details if RED or AMBER -->
                                            <?php if (!empty($q['explain_reason'])): ?>
                                                <div style="margin-top: 5px; background-color: #06101e; border-left: 3px solid <?= $lightColor ?>; padding: 5px 8px; font-size: 11px; color: #cbd5e1;">
                                                    <strong style="color: <?= $lightColor ?>;">Deficiency Note:</strong> <?= htmlspecialchars($q['explain_reason']) ?>
                                                    <?php if (!empty($q['target_cure_date'])): ?>
                                                        <span style="display: block; color: #94a3b8; margin-top: 2px;">Target Cure Date: <strong><?= htmlspecialchars($q['target_cure_date']) ?></strong></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (!empty($q['client_message']) && in_array($light, ['RED', 'AMBER'])): ?>
                                                <div style="margin-top: 5px; color: #94a3b8; font-size: 11px; font-style: italic;">
                                                    <?= htmlspecialchars($q['client_message']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color: #64748b; font-size: 11px; font-style: italic; background-color: #0a172c; padding: 8px 10px; border-radius: 4px;">
                                        No questions answered in this phase yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>

            <p style="margin-top: 24px; color: #94a3b8; font-size: 12px;">
                Sincerely,<br>
                <strong style="color: #ffffff; font-size: 13px;">United Five Construction, Inc.</strong><br>
                <span>Lead Qualification &amp; Estimating Division &middot; 1 World Trade Center, Suite 8500, New York, NY 10007</span>
            </p>
        </div>
    <?php
        $bodyHtml = ob_get_clean();

        return self::sendHtmlEmail($recipientEmail, $subject, $bodyHtml, $assessmentId, 'LEAD_SUMMARY');
    }

    /**

     * Sends automated SLA Reminder to Assessor (triggered every 3 days post Phase 1 completion).
     */
    /**
     * Sends automated SLA Reminder.
     *
     * TESTING:
     * - Set $testMode = true;
     * - Test emails will go to:
     *   tidedit245@daugr.com
     *   gesol31719@airhemp.com
     * - Run the related cron every 1 minute during testing.
     *
     * PRODUCTION:
     * - Set $testMode = false;
     * - Emails will go to the 4 production recipients.
     * - Restore the normal production cron schedule afterward.
     */
    public static function sendSlaReminderEmail(
        array $assessment,
        int $daysElapsed
    ): bool {

        /*
     * ============================================================
     * TEST MODE
     * ============================================================
     *
     * true  = send only to test email addresses
     * false = send to production email addresses
     */
        $testMode = true;


        /*
     * TEST RECIPIENTS
     */
        $testEmails = [
            'tidedit245@daugr.com',
            'gesol31719@airhemp.com',
        ];


        /*
     * PRODUCTION RECIPIENTS
     */
        $productionEmails = [
            'alib@unitedfiveconstruct.com',
            'ujavaid@unitedfiveconstruct.com',
            'apm1@unitedfiveconstruct.com',
            'apm2@unitedfiveconstruct.com',
        ];


        /*
     * Automatically choose recipients based on test mode.
     */
        $assessorEmails = $testMode
            ? $testEmails
            : $productionEmails;


        $assessmentId = (int) $assessment['id'];

        $assessmentNo = htmlspecialchars(
            $assessment['assessment_number']
                ?? ('UFC-' . $assessmentId)
        );

        $clientName = htmlspecialchars(
            $assessment['client_name'] ?? 'Client'
        );

        $projectName = htmlspecialchars(
            $assessment['project_name'] ?? $clientName
        );

        $assessorName = htmlspecialchars(
            $assessment['assessor_name'] ?? 'Assessor'
        );

        $p1CompletedAt = DateService::format(
            $assessment['phase_1_completed_at'] ?? null,
            'M j, Y H:i'
        ) ?? '—';


        /*
     * Email subject
     */
        $subject = $testMode
            ? "[TEST MODE] SLA Alert (Day {$daysElapsed}) — Assessment #{$assessmentNo} ({$projectName})"
            : "SLA Alert (Day {$daysElapsed}) — Assessment #{$assessmentNo} ({$projectName})";


        /*
     * Production assessment URL
     */
        $assessmentUrl =
            'https://pre-assessments.unitedfiveconstruct.com'
            . BASE_URL
            . '/admin/assessment.php?id='
            . $assessmentId;


        ob_start();
    ?>

        <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">

            <?php if ($testMode): ?>

                <div
                    style="
                    background-color: #7c2d12;
                    border: 1px solid #f97316;
                    padding: 10px 14px;
                    border-radius: 6px;
                    margin-bottom: 16px;
                    color: #ffffff;
                    font-weight: bold;
                ">
                    TEST MODE — Temporary SLA Email Test
                </div>

            <?php endif; ?>


            <div
                style="
                background-color: #451a03;
                border: 1px solid #d97706;
                padding: 12px 16px;
                border-radius: 6px;
                margin-bottom: 16px;
            ">
                <strong
                    style="
                    color: #fbbf24;
                    font-size: 15px;
                ">
                    2-Week SLA Timer Reminder
                    (Day <?= $daysElapsed ?>)
                </strong>
            </div>


            <p style="color: #ffffff;">
                Hello <?= $assessorName ?>,
            </p>


            <p>
                This is an automated 3-day reminder for assessment
                #<strong><?= $assessmentNo ?></strong>
                for lead
                <strong><?= $clientName ?></strong>
                (<?= $projectName ?>).
            </p>


            <p>
                Phase 1 was completed on
                <strong><?= $p1CompletedAt ?></strong>
                (<strong><?= $daysElapsed ?> days elapsed</strong>).

                The 2-Week SLA timer is currently active.
            </p>


            <div
                style="
                background-color: #06101e;
                border: 1px solid #1e3e68;
                padding: 14px;
                border-radius: 6px;
                margin: 16px 0;
            ">

                <div
                    style="
                    color: #94a3b8;
                    font-size: 12px;
                    text-transform: uppercase;
                ">
                    Required Action:
                </div>

                <div
                    style="
                    color: #ffffff;
                    font-weight: bold;
                    margin-top: 4px;
                ">
                    Complete the 4 lifecycle milestones or set the final
                    assessment status to stop the SLA timer.
                </div>

            </div>


            <p style="margin-top: 20px;">

                <a
                    href="<?= htmlspecialchars($assessmentUrl) ?>"
                    style="
                    display: inline-block;
                    background-color: #c9a84c;
                    color: #060f1e;
                    font-weight: bold;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 6px;
                ">
                    Open Assessment Detail Inspector &rarr;
                </a>

            </p>

        </div>

        <?php

        $bodyHtml = ob_get_clean();

        $allSent = true;


        /*
     * Send separately to every recipient.
     */
        foreach ($assessorEmails as $recipientEmail) {

            $sent = self::sendHtmlEmail(
                $recipientEmail,
                $subject,
                $bodyHtml,
                $assessmentId,
                $testMode
                    ? 'SLA_REMINDER_TEST'
                    : 'SLA_REMINDER'
            );

            if (!$sent) {
                $allSent = false;
            }
        }


        return $allSent;
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
