<?php

/**
 * components/report-pdf-template.php
 * ─────────────────────────────────────────────────────────────────────────────
 * PDF & Web Preview Template matching the UFC Master Framework exact styling.
 * Supports both standalone Dompdf output and interactive web preview.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/DateService.php';
require_once __DIR__ . '/../components/report-body.php';

function generateAssessmentPdfHtml(int $assessmentId, bool $isWebPreview = false): string
{
    $pdo = getDbConnection();
    $data = getAssessmentReportData($assessmentId);

    if (empty($data)) {
        return '<div style="color:#ffffff; background:#060f1e; padding:40px; text-align:center;"><h1>Assessment Not Found</h1></div>';
    }

    $assessment   = $data['assessment'];
    $phaseResults = $data['phaseResults'];
    $flags        = $data['flags'];
    $overallScore = (float)$data['overallScore'];
    $verdict      = $data['verdict'];

    // Load full itemized questions for each phase
    $stmtQs = $pdo->prepare("
        SELECT q.*, aa.answer_value, aa.score, aa.points_possible, aa.status_light, aa.trigger_fired, aa.is_applicable
        FROM `questions` q
        LEFT JOIN `assessment_answers` aa ON q.id = aa.question_id AND aa.assessment_id = ?
        ORDER BY q.phase_id ASC, q.order_index ASC
    ");
    $stmtQs->execute([$assessmentId]);
    $allQuestions = $stmtQs->fetchAll(PDO::FETCH_ASSOC);

    $questionsByPhase = [];
    foreach ($allQuestions as $q) {
        $questionsByPhase[(int)$q['phase_id']][] = $q;
    }

    // Verdict metadata
    $verdictText = match ($verdict) {
        'GO'          => 'GO — PROCEED TO PROPOSAL',
        'NO-GO'       => 'NO-GO — HARD REJECT',
        'ESCALATED'   => 'HOLD — ESCALATED TO CEO',
        'IN_PROGRESS' => 'IN PROGRESS',
        default       => 'HOLD — REQUIREMENTS OUTSTANDING',
    };
    $verdictColor = match ($verdict) {
        'GO'          => '#34d399',
        'NO-GO'       => '#f87171',
        'ESCALATED'   => '#c084fc',
        'IN_PROGRESS' => '#60a5fa',
        default       => '#fbbf24',
    };
    $verdictBg = match ($verdict) {
        'GO'          => '#064e3b',
        'NO-GO'       => '#450a0a',
        'ESCALATED'   => '#3b0764',
        'IN_PROGRESS' => '#172554',
        default       => '#451a03',
    };
    $verdictBorder = match ($verdict) {
        'GO'          => '#059669',
        'NO-GO'       => '#dc2626',
        'ESCALATED'   => '#9333ea',
        'IN_PROGRESS' => '#2563eb',
        default       => '#d97706',
    };

    $riskText = match (true) {
        $overallScore >= 80.0 => 'Low Risk',
        $overallScore >= 65.0 => 'Moderate Risk',
        $overallScore >= 50.0 => 'High Risk',
        default               => 'Critical Risk',
    };
    $riskColor = match (true) {
        $overallScore >= 80.0 => '#34d399',
        $overallScore >= 65.0 => '#fbbf24',
        default               => '#f87171',
    };

    $projectName   = htmlspecialchars($assessment['project_name'] ?? $assessment['client_name'] ?? 'Unnamed Project');
    $clientName    = htmlspecialchars($assessment['client_name'] ?? '—');
    $clientEmail   = htmlspecialchars($assessment['client_email'] ?? '—');
    $clientPhone   = htmlspecialchars($assessment['client_phone'] ?? '—');
    $assessorName  = htmlspecialchars($assessment['assessor_name'] ?? 'Staff Assessor');
    $dateDisplay   = DateService::nowDisplay();
    $assessmentNo  = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));
    $statusDisplay = str_replace('_', ' ', $assessment['status'] ?? 'IN PROGRESS');
    $createdAt     = DateService::format($assessment['created_at']   ?? null, 'M j, Y', null, '—') ?? '—';
    $completedAt   = DateService::format($assessment['completed_at'] ?? null, 'M j, Y', null, '—') ?? '—';

    // Fetch tier name
    $tierName = '—';
    if (!empty($assessment['tier_id'])) {
        $tierStmt = $pdo->prepare("SELECT name FROM tiers WHERE id = ? LIMIT 1");
        $tierStmt->execute([$assessment['tier_id']]);
        $tierRow  = $tierStmt->fetch(PDO::FETCH_ASSOC);
        $tierName = htmlspecialchars($tierRow['name'] ?? '—');
    }

    // Derive contract type from project_type field (gov / priv / or raw string)
    $projectTypeRaw  = $assessment['project_type'] ?? '';
    $contractTypeMap = ['gov' => 'Government', 'priv' => 'Private', 'government' => 'Government', 'private' => 'Private'];
    $contractType    = htmlspecialchars($contractTypeMap[strtolower($projectTypeRaw)] ?? ($projectTypeRaw ?: '—'));

    ob_start();
?>
    <?php if (!$isWebPreview): ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title><?= $projectName ?> - Assessment Report</title>
            <style>
                <?php else: ?><style><?php endif; ?>@page {
                    margin: 24pt 24pt 32pt 24pt;
                    size: letter portrait;
                }

                *,
                *::before,
                *::after {
                    box-sizing: border-box;
                }

                <?php if (!$isWebPreview): ?>
                body,
                .pdf-document-root {
                    font-family: 'Helvetica', 'Arial', sans-serif;
                    font-size: 8.5pt;
                    line-height: 1.35;
                    color: #1e293b;
                    background: #ffffff;
                    margin: 0;
                    padding: 0;
                }
                <?php else: ?>
                .pdf-document-root {
                    font-family: 'Helvetica', 'Arial', sans-serif;
                    font-size: 8.5pt;
                    line-height: 1.35;
                    color: #1e293b;
                    margin: 0;
                    padding: 0;
                }
                <?php endif; ?>

                <?php if ($isWebPreview): ?>.pdf-preview-sheet {
                    width: 850px;
                    margin: 0 auto;
                    background: #ffffff;
                    padding: 24pt;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
                    color: #1e293b;
                    box-sizing: border-box;
                }

                @media print {
                    .no-print {
                        display: none !important;
                    }

                    .pdf-preview-sheet {
                        width: 100% !important;
                        min-width: 100% !important;
                        max-width: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        border: none !important;
                        box-shadow: none !important;
                    }
                }

                <?php endif; ?>

                /* Container Box - Rich Navy */
                .report-card {
                    background-color: #081528;
                    border: 1pt solid #1e3e68;
                    border-radius: 6pt;
                    padding: 10pt 12pt;
                    margin-bottom: 10pt;
                    color: #e2e8f0;
                }

                /* Section Header with Vertical Gold Bar */
                .section-header {
                    margin-bottom: 8pt;
                }

                .section-title {
                    font-family: 'Georgia', serif;
                    font-size: 12pt;
                    font-weight: bold;
                    color: #f8fafc;
                    margin: 0;
                    padding-left: 5pt;
                    border-left: 3pt solid #c9a84c;
                    line-height: 1.2;
                }

                /* Header / Letterhead */
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    border-bottom: 2pt solid #c9a84c;
                    padding-bottom: 8pt;
                    margin-bottom: 12pt;
                }

                .header-table td {
                    vertical-align: middle;
                }

                .brand-title {
                    font-family: 'Georgia', serif;
                    font-size: 14pt;
                    font-weight: bold;
                    color: #0d1f3c;
                    letter-spacing: 0.5pt;
                    margin: 0;
                }

                .brand-sub {
                    font-size: 7.5pt;
                    color: #64748b;
                    margin-top: 2pt;
                    text-transform: uppercase;
                    letter-spacing: 0.8pt;
                }

                .report-badge {
                    text-align: right;
                    font-size: 8pt;
                    color: #334155;
                }

                /* Metadata Table */
                .meta-wrapper {
                    background-color: #0d1f3c;
                    border: 1pt solid #1e3e68;
                    border-radius: 6pt;
                    padding: 10pt 5pt;
                    overflow: hidden;
                    margin-bottom: 10pt;
                }

                .meta-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 0;
                }

                .meta-table td {
                    padding: 5pt 8pt;
                    background-color: #0d1f3c;
                    border: 1pt solid #1e3e68;
                    vertical-align: top;
                    width: 25%;
                }

                .meta-table tr:first-child td {
                    border-top: none;
                }

                .meta-table tr:last-child td {
                    border-bottom: none;
                }

                .meta-table td:first-child {
                    border-left: none;
                }

                .meta-table td:last-child {
                    border-right: none;
                }

                .meta-label {
                    color: #94a3b8;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 6.5pt;
                    letter-spacing: 0.5pt;
                    margin-bottom: 2pt;
                }

                .meta-val {
                    color: #ffffff;
                    font-weight: bold;
                    font-size: 8pt;
                }

                /* Executive Scorecard */
                .scorecard {
                    /* width: 100%; */
                    background-color: #0d1f3c;
                    border: 1pt solid #1e3e68;
                    border-radius: 6pt;
                    padding: 10pt 12pt;
                    margin-bottom: 12pt;
                    text-align: center;
                    overflow: hidden;
                }

                .score-large {
                    font-size: 26pt;
                    font-weight: bold;
                    color: #c9a84c;
                    line-height: 1;
                    margin: 4pt 0;
                }

                .verdict-pill {
                    display: inline-block;
                    font-size: 9pt;
                    font-weight: bold;
                    padding: 3pt 12pt;
                    border-radius: 3pt;
                    background-color: <?= $verdictBg ?>;
                    color: <?= $verdictColor ?>;
                    border: 1pt solid <?= $verdictBorder ?>;
                    margin-top: 4pt;
                }

                /* Phase Breakdown Card Items */
                .phase-item-box {
                    background-color: #06101e;
                    border: 1pt solid #1a365d;
                    border-radius: 6pt;
                    padding: 8pt 10pt;
                    margin-bottom: 8pt;
                }

                .phase-top-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 4pt;
                }

                .phase-top-table td {
                    vertical-align: middle;
                }

                .pill-phase-id {
                    display: inline-block;
                    background-color: #102847;
                    color: #60a5fa;
                    border: 1pt solid #1d4ed8;
                    font-weight: bold;
                    font-size: 7.5pt;
                    padding: 1pt 5pt;
                    border-radius: 3pt;
                    margin-right: 4pt;
                }

                .phase-title-text {
                    font-size: 9pt;
                    font-weight: bold;
                    color: #ffffff;
                    text-transform: uppercase;
                    margin-right: 6pt;
                }

                .pill-thresh-wt {
                    display: inline-block;
                    background-color: #0d1f3c;
                    color: #cbd5e1;
                    border: 1pt solid #1e3e68;
                    font-size: 7pt;
                    font-family: monospace;
                    padding: 1.5pt 5pt;
                    border-radius: 3pt;
                }

                .pill-flags {
                    display: inline-block;
                    background-color: #451a03;
                    color: #fbbf24;
                    border: 1pt solid #d97706;
                    font-size: 7.5pt;
                    font-weight: bold;
                    padding: 1.5pt 6pt;
                    border-radius: 3pt;
                    margin-right: 6pt;
                }

                .phase-score-big {
                    font-size: 11pt;
                    font-weight: bold;
                    color: #34d399;
                    margin-right: 6pt;
                }

                .pill-gate-pass {
                    display: inline-block;
                    background-color: #064e3b;
                    color: #34d399;
                    border: 1pt solid #059669;
                    font-size: 7.5pt;
                    font-weight: bold;
                    padding: 1.5pt 6pt;
                    border-radius: 3pt;
                }

                .pill-gate-hold {
                    display: inline-block;
                    background-color: #451a03;
                    color: #fbbf24;
                    border: 1pt solid #d97706;
                    font-size: 7.5pt;
                    font-weight: bold;
                    padding: 1.5pt 6pt;
                    border-radius: 3pt;
                }

                .pill-gate-stop {
                    display: inline-block;
                    background-color: #450a0a;
                    color: #f87171;
                    border: 1pt solid #dc2626;
                    font-size: 7.5pt;
                    font-weight: bold;
                    padding: 1.5pt 6pt;
                    border-radius: 3pt;
                }

                /* Progress Bar */
                .progress-track {
                    width: 100%;
                    height: 5pt;
                    background-color: #1e293b;
                    border-radius: 3pt;
                    overflow: hidden;
                    margin: 4pt 0;
                }

                .progress-fill {
                    height: 100%;
                    background-color: #10b981;
                    border-radius: 3pt;
                }

                .progress-fill-amber {
                    height: 100%;
                    background-color: #f59e0b;
                    border-radius: 3pt;
                }

                /* Stats Row Below Progress Bar */
                .phase-stats-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 7.5pt;
                    margin-top: 2pt;
                }

                .phase-stats-table td {
                    vertical-align: middle;
                }

                .dot-indicator {
                    display: inline-block;
                    width: 5pt;
                    height: 5pt;
                    border-radius: 50%;
                    margin-right: 2pt;
                    vertical-align: middle;
                }

                .dot-red {
                    background-color: #ef4444;
                }

                .dot-amber {
                    background-color: #eab308;
                }

                /* Financial Risk Profile Grid */
                .financial-grid-table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 6pt;
                }

                .financial-metric-card {
                    background-color: #06101e;
                    border: 1pt solid #059669;
                    border-radius: 6pt;
                    padding: 8pt 10pt;
                }

                .fin-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .fin-label {
                    font-size: 8pt;
                    font-weight: 600;
                    color: #f1f5f9;
                    text-align: left;
                }

                .fin-val {
                    font-size: 8pt;
                    font-weight: bold;
                    color: #34d399;
                    text-align: right;
                }

                /* Flags Box */
                .flag-group-header {
                    font-size: 8pt;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 0.5pt;
                    padding-bottom: 3pt;
                    margin-bottom: 6pt;
                    border-bottom: 1pt solid #1e3e68;
                }

                .flag-item-box {
                    background-color: #06101e;
                    border: 1pt solid #1e3e68;
                    border-left: 3.5pt solid #ef4444;
                    border-radius: 4pt;
                    padding: 6pt 8pt;
                    margin-bottom: 6pt;
                    font-size: 7.5pt;
                }

                .flag-item-box.red-flag {
                    border-left-color: #ef4444;
                }

                .flag-item-box.amber-flag {
                    border-left-color: #f59e0b;
                }

                .flag-item-box.resolvable {
                    border-left-color: #f59e0b;
                }

                .flag-item-box.internal {
                    border-left-color: #3b82f6;
                }

                .flag-badge {
                    display: inline-block;
                    font-size: 6.5pt;
                    font-weight: bold;
                    padding: 1pt 4pt;
                    border-radius: 3pt;
                    text-transform: uppercase;
                    vertical-align: middle;
                }

                .flag-badge-qnum {
                    background-color: #1a3a5c;
                    color: #c9a84c;
                    border: 0.5pt solid #234d7a;
                    font-family: monospace;
                }

                .flag-badge-red {
                    background-color: #450a0a;
                    color: #f87171;
                    border: 0.5pt solid #dc2626;
                }

                .flag-badge-amber {
                    background-color: #451a03;
                    color: #fbbf24;
                    border: 0.5pt solid #d97706;
                }

                /* Itemized Table */
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 8pt;
                    font-size: 7.5pt;
                }

                .data-table th {
                    background-color: #0f274a;
                    color: #ffffff;
                    text-align: left;
                    padding: 4pt 6pt;
                    font-weight: bold;
                    font-size: 7pt;
                    text-transform: uppercase;
                    border-bottom: 1pt solid #1e3e68;
                }

                .data-table td {
                    padding: 3.5pt 6pt;
                    border-bottom: 1pt solid #162a4a;
                    vertical-align: middle;
                    background-color: #06101e;
                    color: #cbd5e1;
                }

                .data-table tr:nth-child(even) td {
                    background-color: #0a182d;
                }

                .light-green {
                    color: #34d399;
                    font-weight: bold;
                }

                .light-amber {
                    color: #fbbf24;
                    font-weight: bold;
                }

                .light-red {
                    color: #f87171;
                    font-weight: bold;
                }

                .page-break {
                    page-break-before: always;
                }

                /* Footer */
                .footer {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 18pt;
                    font-size: 7pt;
                    color: #64748b;
                    border-top: 1pt solid #1e3e68;
                    padding-top: 3pt;
                    text-align: center;
                }
            </style>
            <?php if (!$isWebPreview): ?>
        </head>

        <body>
        <?php endif; ?>

        <?php if ($isWebPreview): ?>
            <div class="pdf-document-root pdf-preview-sheet">
            <?php endif; ?>

            <!-- Header / Letterhead -->
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="brand-title">UNITED FIVE CONSTRUCTION, INC.</div>
                        <div class="brand-sub">Client Pre-Assessment &amp; Qualification Framework</div>
                    </td>
                    <td style="width: 30%;" class="report-badge">
                        <strong style="color: #c9a84c; font-size: 9pt;"><?= $assessmentNo ?></strong><br>
                        <span style="color: #94a3b8; font-size: 7.5pt;">Generated: <?= $dateDisplay ?></span>
                    </td>
                </tr>
            </table>

            <!-- Assessment Metadata Card — Single Unified Table -->
            <div class="meta-wrapper">
                <table class="meta-table">
                    <tr>
                        <td style="width: 24%;">
                            <div class="meta-label">Project</div>
                            <div class="meta-val"><?= $projectName ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Status</div>
                            <div class="meta-val" style="color: #60a5fa;"><?= htmlspecialchars($statusDisplay) ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Tier</div>
                            <div class="meta-val" style="color: #c9a84c;"><?= $tierName ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Client</div>
                            <div class="meta-val"><?= $clientName ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 24%;">
                            <div class="meta-label">Contact</div>
                            <div class="meta-val"><?= $clientPhone ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Email</div>
                            <div class="meta-val" style="font-size: 7.5pt;"><?= $clientEmail ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Created</div>
                            <div class="meta-val"><?= $createdAt ?></div>
                        </td>
                        <td style="width: 24%;">
                            <div class="meta-label">Completed On</div>
                            <div class="meta-val"><?= $completedAt ?></div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Executive Scorecard -->
            <div class="scorecard">
                <div style="font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1.5pt; color: #94a3b8;">
                    Weighted Qualification Score
                </div>
                <div class="score-large"><?= number_format($overallScore, 1) ?>%</div>
                <div style="font-size: 8pt; color: #cbd5e1; margin-bottom: 4pt;">
                    Risk Profile: <strong style="color: <?= $riskColor ?>;"><?= $riskText ?></strong>
                </div>
                <div class="verdict-pill"><?= $verdictText ?></div>
            </div>

            <!-- ══ PHASE BREAKDOWN SECTION (Exact Screenshot 1 Match) ═══════════════ -->
            <div class="report-card">
                <div class="section-header">
                    <div class="section-title">Phase Breakdown</div>
                </div>

                <?php foreach ($phaseResults as $p):
                    $pScoreEarned   = (float)($p['score_earned'] ?? 0);
                    $pScorePossible = (float)($p['score_possible'] ?? 0);
                    $pScorePercent  = (float)($p['score_percent'] ?? 0);
                    $pThreshold     = (float)($p['threshold'] ?? 65.0);
                    $pWeight        = (float)($p['weight'] ?? 0.25);
                    $pStatus        = $p['status'] ?? 'PASS';
                    $pFlagsCount    = (int)($p['red_count'] ?? 0) + (int)($p['amber_count'] ?? 0);
                    $redCount       = (int)($p['red_count'] ?? 0);
                    $amberCount     = (int)($p['amber_count'] ?? 0);

                    $badgeClass = ($pStatus === 'PASS') ? 'pill-gate-pass' : (($pStatus === 'FAIL_STOP') ? 'pill-gate-stop' : 'pill-gate-hold');
                    $fillClass  = ($pStatus === 'PASS') ? 'progress-fill' : 'progress-fill-amber';
                    $barWidth   = max(2, min(100, $pScorePercent));
                ?>
                    <div class="phase-item-box">
                        <!-- Top Line: Phase Pill + Title + Pass/Wt pill on left; Flags + Score + Gate on right -->
                        <table class="phase-top-table">
                            <tr>
                                <td style="text-align: left;">
                                    <span class="pill-phase-id">P<?= $p['number'] ?></span>
                                    <span class="phase-title-text"><?= htmlspecialchars($p['title']) ?></span>
                                    <span class="pill-thresh-wt">Pass &ge; <?= number_format($pThreshold, 1) ?>% &middot; Wt <?= number_format($pWeight * 100, 1) ?>%</span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($pFlagsCount > 0): ?>
                                        <span class="pill-flags"><?= $pFlagsCount ?> flag<?= $pFlagsCount !== 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <span class="phase-score-big"><?= number_format($pScorePercent, 1) ?>%</span>
                                    <span class="<?= $badgeClass ?>"><?= $pStatus ?></span>
                                </td>
                            </tr>
                        </table>

                        <!-- Progress Bar -->
                        <div class="progress-track">
                            <div class="<?= $fillClass ?>" style="width: <?= $barWidth ?>%;"></div>
                        </div>

                        <!-- Bottom Stats Line -->
                        <table class="phase-stats-table">
                            <tr>
                                <td style="color: #94a3b8; text-align: left;">
                                    Score: <strong style="color: #f1f5f9;"><?= number_format($pScoreEarned, 1) ?> / <?= number_format($pScorePossible, 1) ?></strong>
                                    &nbsp;&nbsp;
                                    Pct: <strong style="color: #f1f5f9;"><?= number_format($pScorePercent, 1) ?>%</strong>
                                    <?php if ($redCount > 0): ?>
                                        &nbsp;&nbsp;
                                        <span style="color: #f87171; font-weight: bold;">
                                            <span class="dot-indicator dot-red"></span><?= $redCount ?> RED
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($amberCount > 0): ?>
                                        &nbsp;&nbsp;
                                        <span style="color: #fbbf24; font-weight: bold;">
                                            <span class="dot-indicator dot-amber"></span><?= $amberCount ?> AMBER
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ══ FINANCIAL RISK PROFILE SECTION (Exact Screenshot 2 Match) ══════ -->
            <div class="report-card">
                <div class="section-header">
                    <div class="section-title">Financial Risk Profile</div>
                </div>

                <table class="financial-grid-table">
                    <tr>
                        <td style="width: 50%;">
                            <div class="financial-metric-card">
                                <table class="fin-table">
                                    <tr>
                                        <td class="fin-label">Profit margin viability</td>
                                        <td class="fin-val"><?= ($overallScore >= 70.0) ? 'Protected' : (($overallScore >= 55.0) ? '<span style="color:#fbbf24;">Marginal</span>' : '<span style="color:#f87171;">At Risk</span>') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <div class="financial-metric-card">
                                <table class="fin-table">
                                    <tr>
                                        <td class="fin-label">Overhead coverage</td>
                                        <td class="fin-val"><?= ($overallScore >= 65.0) ? 'Adequate' : '<span style="color:#f87171;">Insufficient</span>' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <div class="financial-metric-card">
                                <table class="fin-table">
                                    <tr>
                                        <td class="fin-label">CEO compensation %</td>
                                        <td class="fin-val"><?= ($overallScore >= 65.0) ? 'Preserved' : '<span style="color:#f87171;">Compressed</span>' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                        <td style="width: 50%;">
                            <div class="financial-metric-card">
                                <table class="fin-table">
                                    <tr>
                                        <td class="fin-label">Savings retention</td>
                                        <td class="fin-val"><?= ($overallScore >= 75.0) ? 'Strong' : (($overallScore >= 60.0) ? '<span style="color:#fbbf24;">Moderate</span>' : '<span style="color:#f87171;">Weak</span>') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ══ DOCUMENTED FLAGS & CONDITIONS ═════════════════════════════════════ -->
            <?php
            $redFlags   = array_values(array_filter($flags, fn($f) => ($f['statusLight'] ?? '') === 'RED'));
            $amberFlags = array_values(array_filter($flags, fn($f) => ($f['statusLight'] ?? '') === 'AMBER'));
            ?>
            <?php if (!empty($flags)): ?>
                <div class="report-card">
                    <div class="section-header">
                        <div class="section-title">Documented Flags &amp; Conditions (<?= count($flags) ?>)</div>
                    </div>

                    <?php if (!empty($redFlags)): ?>
                        <div class="flag-group-header" style="color: #f87171; margin-top: 4pt;">
                            <span class="dot-indicator dot-red"></span>RED Flags (Critical Deficiencies) (<?= count($redFlags) ?>)
                        </div>
                        <?php foreach ($redFlags as $f): ?>
                            <div class="flag-item-box red-flag">
                                <div style="color: #ffffff;">
                                    <span class="flag-badge flag-badge-qnum">Q<?= htmlspecialchars($f['questionNumber']) ?></span>
                                    <strong><?= htmlspecialchars($f['questionText']) ?></strong>
                                    <?php if (!empty($f['owner'])): ?>
                                        <span style="color: #94a3b8; font-size: 7pt;">[<?= htmlspecialchars($f['owner']) ?>]</span>
                                    <?php endif; ?>
                                    <?php if (!empty($f['trigger']) && $f['trigger'] !== 'NONE'): ?>
                                        <span class="flag-badge flag-badge-red"><?= htmlspecialchars($f['trigger']) ?></span>
                                    <?php endif; ?>
                                    <span class="flag-badge flag-badge-red">RED</span>
                                </div>
                                <?php if (!empty($f['reason'])): ?>
                                    <div style="margin-top: 3pt; color: #cbd5e1;"><strong>Deficiency / Reason:</strong> <?= htmlspecialchars($f['reason']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($f['responsibleParty']) || !empty($f['targetCureDate'])): ?>
                                    <div style="margin-top: 2pt; color: #94a3b8; font-size: 7pt;">
                                        <?php if (!empty($f['responsibleParty'])): ?>
                                            Responsible Party: <strong style="color: #e2e8f0;"><?= htmlspecialchars($f['responsibleParty']) ?></strong> &middot;
                                        <?php endif; ?>
                                        <?php if (!empty($f['targetCureDate'])): ?>
                                            Target Cure Date: <strong style="color: #e2e8f0;"><?= DateService::format($f['targetCureDate'], 'M j, Y') ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($amberFlags)): ?>
                        <div class="flag-group-header" style="color: #fbbf24; margin-top: <?= !empty($redFlags) ? '8pt' : '4pt' ?>;">
                            <span class="dot-indicator dot-amber"></span>AMBER Flags (Warnings &amp; Cautions) (<?= count($amberFlags) ?>)
                        </div>
                        <?php foreach ($amberFlags as $f): ?>
                            <div class="flag-item-box amber-flag">
                                <div style="color: #ffffff;">
                                    <span class="flag-badge flag-badge-qnum">Q<?= htmlspecialchars($f['questionNumber']) ?></span>
                                    <strong><?= htmlspecialchars($f['questionText']) ?></strong>
                                    <?php if (!empty($f['owner'])): ?>
                                        <span style="color: #94a3b8; font-size: 7pt;">[<?= htmlspecialchars($f['owner']) ?>]</span>
                                    <?php endif; ?>
                                    <?php if (!empty($f['trigger']) && $f['trigger'] !== 'NONE'): ?>
                                        <span class="flag-badge flag-badge-amber"><?= htmlspecialchars($f['trigger']) ?></span>
                                    <?php endif; ?>
                                    <span class="flag-badge flag-badge-amber">AMBER</span>
                                </div>
                                <?php if (!empty($f['reason'])): ?>
                                    <div style="margin-top: 3pt; color: #cbd5e1;"><strong>Deficiency / Reason:</strong> <?= htmlspecialchars($f['reason']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($f['responsibleParty']) || !empty($f['targetCureDate'])): ?>
                                    <div style="margin-top: 2pt; color: #94a3b8; font-size: 7pt;">
                                        <?php if (!empty($f['responsibleParty'])): ?>
                                            Responsible Party: <strong style="color: #e2e8f0;"><?= htmlspecialchars($f['responsibleParty']) ?></strong> &middot;
                                        <?php endif; ?>
                                        <?php if (!empty($f['targetCureDate'])): ?>
                                            Target Cure Date: <strong style="color: #e2e8f0;"><?= DateService::format($f['targetCureDate'], 'M j, Y') ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="report-card">
                    <div style="color: #34d399; font-weight: bold; font-size: 8pt; padding: 4pt 0;">
                        &#10003; No flags or conditions documented — all items cleared.
                    </div>
                </div>
            <?php endif; ?>

            <!-- ══ ITEMIZED ASSESSMENT RESPONSES ═════════════════════════════════════ -->
            <div class="page-break"></div>
            <div class="report-card">
                <div class="section-header">
                    <div class="section-title">Itemized Assessment Responses</div>
                </div>

                <?php foreach ($phaseResults as $p):
                    $qs = $questionsByPhase[$p['id']] ?? [];
                    if (empty($qs)) continue;
                ?>
                    <div style="font-weight: bold; font-size: 8.5pt; color: #60a5fa; margin-top: 8pt; margin-bottom: 4pt; border-left: 2pt solid #c9a84c; padding-left: 4pt;">
                        Phase <?= $p['number'] ?>: <?= htmlspecialchars($p['title']) ?>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 8%;">Q#</th>
                                <th style="width: 52%;">Question</th>
                                <th style="width: 10%;">Owner</th>
                                <th style="width: 18%;">Answer</th>
                                <th style="width: 6%;">Status</th>
                                <th style="width: 6%; text-align: right;">Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qs as $q):
                                $light = $q['status_light'] ?? 'PENDING';
                                $lightClass = ($light === 'GREEN') ? 'light-green' : (($light === 'AMBER') ? 'light-amber' : (($light === 'RED') ? 'light-red' : ''));
                                $ansDisplay = formatAnswerValue($q['answer_value'] ?? null, $q);
                                if (strlen($ansDisplay) > 28) {
                                    $ansDisplay = substr($ansDisplay, 0, 25) . '...';
                                }
                            ?>
                                <tr>
                                    <td><strong style="color: #c9a84c;"><?= $q['question_number'] ?></strong></td>
                                    <td style="color: #f1f5f9;"><?= htmlspecialchars($q['question_text']) ?></td>
                                    <td style="color: #94a3b8;"><?= $q['owner'] ?></td>
                                    <td><?= htmlspecialchars($ansDisplay) ?></td>
                                    <td><span class="<?= $lightClass ?>"><?= $light ?></span></td>
                                    <td style="text-align: right; color: #ffffff;"><?= isset($q['score']) ? number_format((float)$q['score'], 0) : '—' ?>/10</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>

            <!-- ══ SIGNATURE & AUTHORITY BLOCK ═══════════════════════════════════════ -->
            <div style="margin-top: 16pt; padding-top: 8pt; border-top: 1pt solid #1e3e68;">
                <table style="width: 100%; border-collapse: collapse; font-size: 7.5pt;">
                    <tr>
                        <td style="width: 50%; padding-right: 20pt;">
                            <div style="border-bottom: 1pt solid #475569; height: 24pt;"></div>
                            <div style="margin-top: 4pt; color: #94a3b8;">
                                <strong style="color: #e2e8f0;">Prepared By:</strong> <?= $assessorName ?><br>
                                Assessor Signature / Date
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 20pt;">
                            <div style="border-bottom: 1pt solid #475569; height: 24pt;"></div>
                            <div style="margin-top: 4pt; color: #94a3b8;">
                                <strong style="color: #e2e8f0;">Executive Review:</strong> United Five Construction, Inc.<br>
                                Authorized Representative / Date
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Bottom page text -->
            <div class="footer">
                United Five Construction, Inc. &bull; Confidential Assessment Document &bull; Assessment #<?= $assessmentNo ?>
            </div>

            <?php if ($isWebPreview): ?>
            </div> <!-- /.pdf-preview-sheet -->
        <?php else: ?>
        </body>

        </html>
    <?php endif; ?>
<?php
    return ob_get_clean();
}
