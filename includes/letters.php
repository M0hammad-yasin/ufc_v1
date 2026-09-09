<?php
/**
 * United Five Construction - Client Letters Engine
 * Implements Requirements Letter and Decline Letter generation per v5.0 spec
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


function generateRequirementsLetterData(int $assessmentId, ?int $phaseNumber = null): array {
    $pdo = getDbConnection();
    $assessment = getAssessmentDetails($assessmentId);
    if (!$assessment) {
        throw new InvalidArgumentException("Assessment not found");
    }

    // Query questions and answers from client-facing phases (Phases 1, 2, 3 — internal Phase 4 excluded)
    // If a specific client phase (1, 2, or 3) is passed, filter to that phase; otherwise query all first 3 phases.
    $params = [$assessmentId];
    $phaseCondition = "p.phase_number IN (1, 2, 3)";
    if ($phaseNumber !== null && $phaseNumber >= 1 && $phaseNumber <= 3) {
        $phaseCondition = "p.phase_number = ?";
        $params[] = $phaseNumber;
    }

    $sql = "
        SELECT 
            q.id AS question_id,
            q.question_number,
            q.question_text,
            q.client_message,
            q.order_index,
            q.owner,
            p.id AS phase_id,
            p.phase_number,
            p.title AS phase_title,
            a.status_light,
            a.answer_value,
            a.trigger_fired,
            eb.responsible_party,
            eb.reason,
            eb.target_cure_date
        FROM assessment_answers a
        JOIN questions q ON a.question_id = q.id
        JOIN phases p ON q.phase_id = p.id
        LEFT JOIN explain_blocks eb ON (eb.assessment_id = a.assessment_id AND eb.question_id = q.id)
        WHERE a.assessment_id = ? 
          AND {$phaseCondition}
          AND q.visibility = 'CLIENT_FACING'
        ORDER BY 
            FIELD(a.status_light, 'RED', 'AMBER', 'GREEN'),
            p.phase_number ASC,
            q.order_index ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute deadline and timeline info for each individual question
    $defaultDeadline = $assessment['hold_deadline_date'] ?: date('Y-m-d', strtotime('+30 days'));
    $today = new DateTime('today');

    foreach ($items as &$item) {
        $status = strtoupper($item['status_light'] ?? 'GREEN');
        $item['status_light'] = $status;
        $cureDate = !empty($item['target_cure_date']) ? $item['target_cure_date'] : null;

        // If deficient (RED/AMBER) and no specific cure date set, fallback to default 30-day deadline
        if (!$cureDate && in_array($status, ['RED', 'AMBER'])) {
            $cureDate = $defaultDeadline;
        }

        $item['deadline_raw'] = $cureDate;
        if ($cureDate) {
            $dueDate = new DateTime($cureDate);
            $diffDays = (int)$today->diff($dueDate)->format('%r%a');
            $item['deadline_formatted'] = date('F j, Y', strtotime($cureDate));
            $item['deadline_short'] = date('M j, Y', strtotime($cureDate));
            $item['days_remaining'] = $diffDays;
            $item['is_overdue'] = ($diffDays < 0);
        } else {
            $item['deadline_formatted'] = null;
            $item['deadline_short'] = null;
            $item['days_remaining'] = null;
            $item['is_overdue'] = false;
        }

        $party = !empty($item['responsible_party']) ? $item['responsible_party'] : (!empty($item['owner']) ? $item['owner'] : 'CLIENT');
        $item['responsible_party'] = $party;
    }
    unset($item);

    // Group items by status: RED, AMBER, and PASSED (GREEN)
    $redItems = [];
    $amberItems = [];
    $passedItems = [];
    $groupedByParty = [];

    foreach ($items as $item) {
        $status = $item['status_light'];
        if ($status === 'RED') {
            $redItems[] = $item;
        } elseif ($status === 'AMBER') {
            $amberItems[] = $item;
        } else {
            $passedItems[] = $item;
        }

        $party = $item['responsible_party'];
        $groupedByParty[$party][] = $item;
    }

    $groupedByStatus = [
        'RED' => [
            'key' => 'RED',
            'title' => 'Critical Outstanding Requirements (RED Items)',
            'short_title' => 'RED Items',
            'badge' => 'CRITICAL HOLD',
            'color' => '#ef4444',
            'bg_color' => '#fef2f2',
            'border_color' => '#fecaca',
            'badge_bg' => '#fee2e2',
            'badge_text' => '#991b1b',
            'items' => $redItems,
            'count' => count($redItems)
        ],
        'AMBER' => [
            'key' => 'AMBER',
            'title' => 'Conditional Requirements (AMBER Items)',
            'short_title' => 'AMBER Items',
            'badge' => 'ATTENTION REQUIRED',
            'color' => '#f59e0b',
            'bg_color' => '#fffbeb',
            'border_color' => '#fde68a',
            'badge_bg' => '#fef3c7',
            'badge_text' => '#92400e',
            'items' => $amberItems,
            'count' => count($amberItems)
        ],
        'PASSED' => [
            'key' => 'PASSED',
            'title' => 'Satisfied & Compliant (Passed Questions)',
            'short_title' => 'Passed Items',
            'badge' => 'COMPLIANT / PASSED',
            'color' => '#10b981',
            'bg_color' => '#f0fdf4',
            'border_color' => '#bbf7d0',
            'badge_bg' => '#dcfce7',
            'badge_text' => '#166534',
            'items' => $passedItems,
            'count' => count($passedItems)
        ]
    ];

    $formattedDeadline = date('F j, Y', strtotime($defaultDeadline));

    return [
        'assessment' => $assessment,
        'phase_number' => $phaseNumber,
        'deadline_formatted' => $formattedDeadline,
        'items' => $items,
        'grouped_items' => $groupedByParty,
        'grouped_by_status' => $groupedByStatus,
        'red_items' => $redItems,
        'amber_items' => $amberItems,
        'passed_items' => $passedItems,
        'counts' => [
            'total' => count($items),
            'red' => count($redItems),
            'amber' => count($amberItems),
            'passed' => count($passedItems)
        ]
    ];
}

function generateDeclineLetterData(int $assessmentId): array {
    $assessment = getAssessmentDetails($assessmentId);
    if (!$assessment) {
        throw new InvalidArgumentException("Assessment not found");
    }

    $reason = $assessment['decline_reason'] ?: 'STOP_TRIGGER';
    $holdDate = $assessment['hold_deadline_date'] ? date('F j, Y', strtotime($assessment['hold_deadline_date'] . ' -30 days')) : date('F j, Y');

    return [
        'assessment' => $assessment,
        'decline_reason' => $reason,
        'initial_letter_date' => $holdDate,
        'date_issued' => date('F j, Y')
    ];
}

/**
 * Generates an official, print-ready HTML document for Dompdf rendering.
 */
function generateRequirementsLetterPdfHtml(array $letterData, ?int $selectedPhase = null): string {
    $assessment = $letterData['assessment'];
    $groupedByStatus = $letterData['grouped_by_status'];
    $counts = $letterData['counts'];
    $dateIssued = date('F j, Y');
    $phaseText = ($selectedPhase && $selectedPhase >= 1 && $selectedPhase <= 3) 
        ? "Phase {$selectedPhase}" 
        : "Client-Facing (Phases 1–3)";

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>UFC Requirements Letter - <?= htmlspecialchars($assessment['assessment_number']) ?></title>
        <style>
            @page {
                margin: 25pt 30pt 35pt 30pt;
                @bottom-right {
                    content: "Page " counter(page) " of " counter(pages);
                    font-size: 8pt;
                    color: #94a3b8;
                }
            }
            body {
                font-family: Helvetica, Arial, sans-serif;
                font-size: 9.5pt;
                line-height: 1.45;
                color: #1e293b;
                background-color: #ffffff;
                margin: 0;
                padding: 0;
            }
            .header-table {
                width: 100%;
                border-collapse: collapse;
                border-bottom: 2pt solid #0d1f3c;
                padding-bottom: 8pt;
                margin-bottom: 12pt;
            }
            .brand-title {
                font-family: 'Times New Roman', Times, serif;
                font-size: 16pt;
                font-weight: bold;
                color: #0d1f3c;
                margin: 0 0 2pt 0;
                letter-spacing: 0.5px;
            }
            .brand-sub {
                font-size: 8pt;
                color: #475569;
                margin: 1pt 0;
            }
            .badge-title {
                display: inline-block;
                background-color: #fef3c7;
                color: #92400e;
                border: 1px solid #fde68a;
                font-size: 8.5pt;
                font-weight: bold;
                padding: 3pt 8pt;
                border-radius: 3pt;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .meta-table {
                width: 100%;
                border-collapse: collapse;
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 4pt;
                margin-bottom: 12pt;
            }
            .meta-table td {
                padding: 6pt 10pt;
                vertical-align: top;
                font-size: 8.5pt;
            }
            .meta-label {
                font-size: 7.5pt;
                text-transform: uppercase;
                font-weight: bold;
                color: #64748b;
                display: block;
                margin-bottom: 2pt;
            }
            .meta-value {
                font-size: 9.5pt;
                font-weight: bold;
                color: #0f172a;
            }
            .notice-box {
                margin-bottom: 12pt;
                font-size: 9pt;
                color: #334155;
            }
            .notice-box p {
                margin: 0 0 6pt 0;
            }
            .callout-box {
                background-color: #fffbeb;
                border-left: 4px solid #f59e0b;
                padding: 7pt 10pt;
                color: #92400e;
                font-weight: 500;
                font-size: 9pt;
                margin: 8pt 0 12pt 0;
            }
            .summary-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 14pt;
            }
            .summary-cell {
                width: 33.33%;
                padding: 6pt 8pt;
                border-radius: 4pt;
                text-align: center;
            }
            .section-header {
                font-size: 11pt;
                font-weight: bold;
                padding: 6pt 8pt;
                border-radius: 3pt;
                margin-top: 14pt;
                margin-bottom: 8pt;
                page-break-after: avoid;
            }
            .item-card {
                border: 1px solid #e2e8f0;
                border-radius: 4pt;
                padding: 8pt 10pt;
                margin-bottom: 8pt;
                page-break-inside: avoid;
                background-color: #ffffff;
            }
            .item-top-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 4pt;
            }
            .deadline-chip {
                display: inline-block;
                padding: 3pt 7pt;
                font-size: 8pt;
                font-weight: bold;
                border-radius: 3pt;
                letter-spacing: 0.3px;
            }
            .deadline-red {
                background-color: #fee2e2;
                color: #991b1b;
                border: 1px solid #fca5a5;
            }
            .deadline-amber {
                background-color: #fef3c7;
                color: #92400e;
                border: 1px solid #fcd34d;
            }
            .deadline-green {
                background-color: #dcfce7;
                color: #166534;
                border: 1px solid #86efac;
            }
            .status-badge {
                display: inline-block;
                padding: 2pt 6pt;
                font-size: 7.5pt;
                font-weight: bold;
                border-radius: 3pt;
                text-transform: uppercase;
            }
            .badge-red {
                background-color: #ef4444;
                color: #ffffff;
            }
            .badge-amber {
                background-color: #f59e0b;
                color: #ffffff;
            }
            .badge-green {
                background-color: #10b981;
                color: #ffffff;
            }
            .phase-chip {
                display: inline-block;
                background-color: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
                font-size: 7.5pt;
                font-weight: bold;
                padding: 2pt 5pt;
                border-radius: 3pt;
                margin-left: 4pt;
            }
            .question-title {
                font-size: 9.5pt;
                font-weight: bold;
                color: #0f172a;
                margin: 4pt 0 4pt 0;
            }
            .client-message-box {
                background-color: #f8fafc;
                border-left: 3px solid #64748b;
                padding: 5pt 8pt;
                font-size: 8.5pt;
                color: #475569;
                font-style: italic;
                margin-top: 4pt;
                border-radius: 2pt;
            }
            .reason-box {
                font-size: 8.5pt;
                color: #334155;
                margin-top: 4pt;
            }
            .footer-table {
                width: 100%;
                border-collapse: collapse;
                border-top: 1px solid #cbd5e1;
                padding-top: 8pt;
                margin-top: 16pt;
                font-size: 8pt;
                color: #64748b;
                page-break-inside: avoid;
            }
        </style>
    </head>
    <body>
        <!-- Header / Letterhead -->
        <table class="header-table">
            <tr>
                <td style="vertical-align: top; width: 65%;">
                    <div class="brand-title">UNITED FIVE CONSTRUCTION, INC.</div>
                    <div class="brand-sub">General Contractor &middot; NYC DOB GC License #625679</div>
                    <div class="brand-sub">1 World Trade Center, Suite 8500, New York, NY 10007</div>
                </td>
                <td style="vertical-align: top; width: 35%; text-align: right;">
                    <div class="badge-title">REQUIREMENTS NOTICE</div>
                    <div style="font-size: 8pt; color: #64748b; margin-top: 4pt;">Date: <?= $dateIssued ?></div>
                    <div style="font-size: 8pt; color: #64748b;">Ref: <?= htmlspecialchars($assessment['assessment_number']) ?></div>
                    <div style="font-size: 8pt; color: #0d1f3c; font-weight: bold;"><?= $phaseText ?></div>
                </td>
            </tr>
        </table>

        <!-- Recipient Details -->
        <table class="meta-table">
            <tr>
                <td style="width: 50%; border-right: 1px solid #e2e8f0;">
                    <span class="meta-label">Client / Developer:</span>
                    <span class="meta-value"><?= htmlspecialchars($assessment['client_name']) ?></span>
                    <div style="color: #475569; font-size: 8.5pt; margin-top: 2pt;"><?= htmlspecialchars($assessment['client_email']) ?></div>
                </td>
                <td style="width: 50%;">
                    <span class="meta-label">Project Location &amp; Scope:</span>
                    <span class="meta-value"><?= htmlspecialchars($assessment['project_address'] ?: 'Address on file') ?></span>
                    <div style="color: #475569; font-size: 8.5pt; margin-top: 2pt;">Scope: <?= htmlspecialchars($assessment['project_type'] ?? 'General Scope') ?></div>
                </td>
            </tr>
        </table>

        <!-- Letter Body Cover Language -->
        <div class="notice-box">
            <p><strong>Thank you for the opportunity to review your project.</strong></p>
            <p>Before United Five Construction issues an estimate, we complete a structured readiness review of the project documentation, the property and the funding. We do this on every project, without exception. It protects you from receiving a number that changes later, and it protects us from committing crew and material to a project that is not yet ready to build.</p>
            <p>The items below are grouped by assessment status (Critical RED, Conditional AMBER, and Compliant PASSED). Each question indicates its designated deadline at the top left. We are not able to produce a firm estimate while critical items remain open.</p>
            <p><strong>This is not a decline.</strong> Send us these items and we will complete the review and move directly to pricing. We are glad to walk through the list with you and your design professional on a call.</p>
            <div class="callout-box">
                If we have not heard from you by <strong>given date</strong>, we will close the file. You are welcome to reopen it at any time.
            </div>
        </div>

        <!-- Executive Summary Cards -->
        <table class="summary-table">
            <tr>
                <td style="padding-right: 4pt;">
                    <div class="summary-cell" style="background-color: #fef2f2; border: 1px solid #fecaca;">
                        <span style="font-size: 13pt; font-weight: bold; color: #dc2626;"><?= $counts['red'] ?></span>
                        <div style="font-size: 7.5pt; font-weight: bold; color: #991b1b; text-transform: uppercase;">Critical (RED)</div>
                    </div>
                </td>
                <td style="padding: 0 2pt;">
                    <div class="summary-cell" style="background-color: #fffbeb; border: 1px solid #fde68a;">
                        <span style="font-size: 13pt; font-weight: bold; color: #d97706;"><?= $counts['amber'] ?></span>
                        <div style="font-size: 7.5pt; font-weight: bold; color: #92400e; text-transform: uppercase;">Conditional (AMBER)</div>
                    </div>
                </td>
                <td style="padding-left: 4pt;">
                    <div class="summary-cell" style="background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                        <span style="font-size: 13pt; font-weight: bold; color: #059669;"><?= $counts['passed'] ?></span>
                        <div style="font-size: 7.5pt; font-weight: bold; color: #166534; text-transform: uppercase;">Compliant (PASSED)</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Grouped Sections by Status -->
        <?php foreach ($groupedByStatus as $statusKey => $group): ?>
            <?php if (!empty($group['items'])): ?>
                <div class="section-header" style="background-color: <?= $group['bg_color'] ?>; color: <?= $group['color'] ?>; border: 1px solid <?= $group['border_color'] ?>;">
                    <?= htmlspecialchars($group['title']) ?> (<?= $group['count'] ?> Items)
                </div>

                <?php foreach ($group['items'] as $item): 
                    $isRed = ($statusKey === 'RED');
                    $isAmber = ($statusKey === 'AMBER');
                    $isPassed = ($statusKey === 'PASSED');
                ?>
                    <div class="item-card" style="border-left: 4px solid <?= $group['color'] ?>;">
                        <table class="item-top-table">
                            <tr>
                                <!-- TOP-LEFT: Question Deadline with High Visibility -->
                                <td style="vertical-align: middle; text-align: left; width: 60%;">
                                    <?php if ($isPassed): ?>
                                        <div class="deadline-chip deadline-green">
                                            PASSED &mdash; NO ACTION REQUIRED
                                        </div>
                                    <?php else: ?>
                                        <div class="deadline-chip <?= $isRed ? 'deadline-red' : 'deadline-amber' ?>">
                                            DEADLINE: <?= htmlspecialchars($item['deadline_formatted'] ?: 'Within 30 Days') ?>
                                            <?php if ($item['days_remaining'] !== null): ?>
                                                <span style="font-size: 7pt; font-weight: normal; margin-left: 4pt;">
                                                    (<?= $item['is_overdue'] ? 'OVERDUE' : ($item['days_remaining'] . ' days left') ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <!-- TOP-RIGHT: Status & Phase Badges -->
                                <td style="vertical-align: middle; text-align: right; width: 40%;">
                                    <span class="status-badge <?= $isRed ? 'badge-red' : ($isAmber ? 'badge-amber' : 'badge-green') ?>">
                                        <?= $item['status_light'] ?>
                                    </span>
                                    <span class="phase-chip">
                                        Phase <?= $item['phase_number'] ?>
                                    </span>
                                    <?php if (!empty($item['responsible_party'])): ?>
                                        <span class="phase-chip" style="color: #0d1f3c; background-color: #e2e8f0;">
                                            <?= htmlspecialchars($item['responsible_party']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <div class="question-title">
                            <span style="color: #64748b; font-family: monospace;">Q<?= htmlspecialchars($item['question_number']) ?>:</span>
                            <?= htmlspecialchars($item['question_text']) ?>
                        </div>

                        <?php if (!empty($item['client_message'])): ?>
                            <div class="client-message-box">
                                <strong>Requirement Guidance:</strong> <?= htmlspecialchars($item['client_message']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['reason'])): ?>
                            <div class="reason-box">
                                <strong>Assessment Note:</strong> <?= htmlspecialchars($item['reason']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isPassed && !empty($item['answer_value'])): ?>
                            <div style="font-size: 8pt; color: #059669; margin-top: 3pt;">
                                <strong>Recorded Compliant Answer:</strong> <?= htmlspecialchars($item['answer_value']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Footer Sign-off -->
        <table class="footer-table">
            <tr>
                <td style="width: 70%; vertical-align: bottom;">
                    <div style="font-weight: bold; color: #0d1f3c; font-size: 9pt;">United Five Construction, Inc.</div>
                    <div>Pre-Assessment Qualification Division &middot; 1 World Trade Center, Suite 8500, New York, NY 10007</div>
                    <div style="margin-top: 2pt; color: #475569;">Deadlines: Refer to individual item dates specified above</div>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: bottom; font-family: monospace; font-size: 7.5pt;">
                    Document Form UFC-REQ-v5<br>
                    Generated: <?= date('Y-m-d H:i') ?> UTC
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

