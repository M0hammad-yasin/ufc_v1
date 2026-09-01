<?php
/**
 * components/report-pdf-template.php
 * ─────────────────────────────────────────────────────────────────────────────
 * PDF HTML Template for Dompdf generation.
 * Generates clean, robust, multi-page PDF HTML matching UFC Master Framework spec.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/DateService.php';
require_once __DIR__ . '/../components/report-body.php';

function generateAssessmentPdfHtml(int $assessmentId): string
{
    $pdo = getDbConnection();
    $data = getAssessmentReportData($assessmentId);

    if (empty($data)) {
        return '<html><body><h1>Assessment Not Found</h1></body></html>';
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
        'GO'    => 'GO — PROCEED TO PROPOSAL',
        'NO-GO' => 'NO-GO — HARD REJECT',
        default => 'HOLD — ESCALATE TO CEO',
    };
    $verdictColor = match ($verdict) {
        'GO'    => '#166534',
        'NO-GO' => '#991b1b',
        default => '#854d0e',
    };
    $verdictBg = match ($verdict) {
        'GO'    => '#dcfce7',
        'NO-GO' => '#fee2e2',
        default => '#fef3c7',
    };

    $riskText = match (true) {
        $overallScore >= 80.0 => 'Low Risk',
        $overallScore >= 65.0 => 'Moderate Risk',
        $overallScore >= 50.0 => 'High Risk',
        default               => 'Critical Risk',
    };
    $riskColor = match (true) {
        $overallScore >= 80.0 => '#166534',
        $overallScore >= 65.0 => '#854d0e',
        default               => '#991b1b',
    };

    $projectName  = htmlspecialchars($assessment['project_name'] ?? $assessment['client_name'] ?? 'Unnamed Project');
    $clientName   = htmlspecialchars($assessment['client_name'] ?? '—');
    $clientEmail  = htmlspecialchars($assessment['client_email'] ?? '—');
    $assessorName = htmlspecialchars($assessment['assessor_name'] ?? 'Staff Assessor');
    $dateDisplay  = DateService::nowDisplay();
    $assessmentNo = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));

    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $projectName ?> - Assessment Report</title>
<style>
    @page {
        margin: 36pt 36pt 45pt 36pt;
    }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 9pt;
        line-height: 1.35;
        color: #1e293b;
        background: #ffffff;
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
    .header-table td {
        vertical-align: top;
    }
    .brand-title {
        font-size: 14pt;
        font-weight: bold;
        color: #0d1f3c;
        letter-spacing: 0.5pt;
        margin: 0;
    }
    .brand-sub {
        font-size: 8pt;
        color: #64748b;
        margin-top: 2pt;
        text-transform: uppercase;
        letter-spacing: 1pt;
    }
    .report-badge {
        text-align: right;
        font-size: 8pt;
        color: #475569;
    }
    .meta-box {
        width: 100%;
        background-color: #f8fafc;
        border: 1pt solid #cbd5e1;
        border-radius: 4pt;
        margin-bottom: 12pt;
        padding: 6pt 10pt;
    }
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
    }
    .meta-table td {
        padding: 2pt 4pt;
    }
    .meta-label {
        color: #64748b;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 7.5pt;
    }
    .meta-val {
        color: #0f172a;
        font-weight: bold;
    }

    /* Executive Scorecard */
    .scorecard {
        width: 100%;
        background: #0d1f3c;
        color: #ffffff;
        border-radius: 4pt;
        padding: 10pt 14pt;
        margin-bottom: 12pt;
        text-align: center;
    }
    .score-large {
        font-size: 28pt;
        font-weight: bold;
        color: #c9a84c;
        line-height: 1;
        margin: 4pt 0;
    }
    .verdict-pill {
        display: inline-block;
        font-size: 9.5pt;
        font-weight: bold;
        padding: 4pt 12pt;
        border-radius: 3pt;
        background-color: <?= $verdictBg ?>;
        color: <?= $verdictColor ?>;
        margin-top: 4pt;
    }

    /* Section Headings */
    .section-title {
        font-size: 10.5pt;
        font-weight: bold;
        color: #0d1f3c;
        border-bottom: 1pt solid #cbd5e1;
        padding-bottom: 3pt;
        margin-top: 14pt;
        margin-bottom: 6pt;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
    }

    /* Table styles */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10pt;
        font-size: 8pt;
    }
    .data-table th {
        background-color: #0d1f3c;
        color: #ffffff;
        text-align: left;
        padding: 4pt 6pt;
        font-weight: bold;
        font-size: 7.5pt;
        text-transform: uppercase;
    }
    .data-table td {
        padding: 4pt 6pt;
        border-bottom: 1pt solid #e2e8f0;
        vertical-align: middle;
    }
    .data-table tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .badge-pass {
        background-color: #dcfce7;
        color: #166534;
        font-weight: bold;
        padding: 1pt 4pt;
        border-radius: 2pt;
        font-size: 7pt;
    }
    .badge-hold {
        background-color: #fef3c7;
        color: #854d0e;
        font-weight: bold;
        padding: 1pt 4pt;
        border-radius: 2pt;
        font-size: 7pt;
    }
    .badge-stop {
        background-color: #fee2e2;
        color: #991b1b;
        font-weight: bold;
        padding: 1pt 4pt;
        border-radius: 2pt;
        font-size: 7pt;
    }

    .status-light-green { color: #16a34a; font-weight: bold; }
    .status-light-amber { color: #d97706; font-weight: bold; }
    .status-light-red   { color: #dc2626; font-weight: bold; }

    .flag-box {
        border-left: 3pt solid #dc2626;
        background: #fff5f5;
        padding: 5pt 8pt;
        margin-bottom: 6pt;
        border-radius: 2pt;
        font-size: 7.5pt;
    }
    .flag-box.resolvable {
        border-left-color: #d97706;
        background: #fffbeb;
    }
    .flag-box.internal {
        border-left-color: #2563eb;
        background: #eff6ff;
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
        height: 20pt;
        font-size: 7.5pt;
        color: #94a3b8;
        border-top: 1pt solid #e2e8f0;
        padding-top: 4pt;
        text-align: center;
    }
</style>
</head>
<body>

    <!-- Header / Letterhead -->
    <table class="header-table">
        <tr>
            <td>
                <div class="brand-title">UNITED FIVE CONSTRUCTION, INC.</div>
                <div class="brand-sub">Client Pre-Assessment &amp; Qualification Framework</div>
            </td>
            <td class="report-badge">
                <strong><?= $assessmentNo ?></strong><br>
                Generated: <?= $dateDisplay ?>
            </td>
        </tr>
    </table>

    <!-- Project & Assessment Meta -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td style="width: 25%;">
                    <div class="meta-label">Project Name</div>
                    <div class="meta-val"><?= $projectName ?></div>
                </td>
                <td style="width: 25%;">
                    <div class="meta-label">Client / Sponsor</div>
                    <div class="meta-val"><?= $clientName ?></div>
                </td>
                <td style="width: 25%;">
                    <div class="meta-label">Assessor</div>
                    <div class="meta-val"><?= $assessorName ?></div>
                </td>
                <td style="width: 25%;">
                    <div class="meta-label">Assessment Status</div>
                    <div class="meta-val"><?= htmlspecialchars(str_replace('_', ' ', $assessment['status'])) ?></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Executive Scorecard -->
    <div class="scorecard">
        <div style="font-size: 8pt; text-transform: uppercase; letter-spacing: 1.5pt; color: #94a3b8;">
            Weighted Qualification Score
        </div>
        <div class="score-large"><?= number_format($overallScore, 1) ?>%</div>
        <div style="font-size: 8pt; color: #cbd5e1; margin-bottom: 4pt;">
            Risk Profile: <strong style="color: <?= $riskColor ?>;"><?= $riskText ?></strong>
        </div>
        <div class="verdict-pill"><?= $verdictText ?></div>
    </div>

    <!-- Phase Gate Summary -->
    <div class="section-title">Phase Gate Evaluations</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%;">Phase</th>
                <th style="width: 44%;">Title</th>
                <th style="width: 12%;">Weight</th>
                <th style="width: 12%;">Pass Threshold</th>
                <th style="width: 12%;">Score Earned</th>
                <th style="width: 12%; text-align: center;">Gate Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($phaseResults as $p): 
                $badge = ($p['status'] === 'PASS') ? 'badge-pass' : (($p['status'] === 'FAIL_STOP') ? 'badge-stop' : 'badge-hold');
            ?>
            <tr>
                <td><strong>Phase <?= $p['number'] ?></strong></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><?= number_format($p['weight'] * 100, 1) ?>%</td>
                <td>&ge; <?= number_format($p['threshold'], 1) ?>%</td>
                <td><strong><?= number_format($p['score_percent'], 1) ?>%</strong> (<?= $p['score_earned'] ?>/<?= $p['score_possible'] ?>)</td>
                <td style="text-align: center;"><span class="<?= $badge ?>"><?= $p['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Financial Risk Profile -->
    <div class="section-title">Financial Risk Profile</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50%;">Metric</th>
                <th style="width: 50%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Profit Margin Viability</strong></td>
                <td><?= ($overallScore >= 70.0) ? '<span class="status-light-green">Protected</span>' : (($overallScore >= 55.0) ? '<span class="status-light-amber">Marginal</span>' : '<span class="status-light-red">At Risk</span>') ?></td>
            </tr>
            <tr>
                <td><strong>Overhead Coverage</strong></td>
                <td><?= ($overallScore >= 65.0) ? '<span class="status-light-green">Adequate</span>' : '<span class="status-light-red">Insufficient</span>' ?></td>
            </tr>
            <tr>
                <td><strong>CEO Compensation %</strong></td>
                <td><?= ($overallScore >= 65.0) ? '<span class="status-light-green">Preserved</span>' : '<span class="status-light-red">Compressed</span>' ?></td>
            </tr>
            <tr>
                <td><strong>Savings Retention</strong></td>
                <td><?= ($overallScore >= 75.0) ? '<span class="status-light-green">Strong</span>' : (($overallScore >= 60.0) ? '<span class="status-light-amber">Moderate</span>' : '<span class="status-light-red">Weak</span>') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Documented Flags & Deficiencies -->
    <?php if (!empty($flags)): ?>
    <div class="section-title">Documented Flags &amp; Conditions (<?= count($flags) ?>)</div>
    <?php foreach ($flags as $f): 
        $boxClass = ($f['severity'] === RPT_CRITICAL) ? '' : (($f['severity'] === RPT_RESOLVABLE) ? 'resolvable' : 'internal');
    ?>
    <div class="flag-box <?= $boxClass ?>">
        <div>
            <strong>[Q<?= htmlspecialchars($f['questionNumber']) ?>] <?= htmlspecialchars($f['questionText']) ?></strong>
            &mdash; <span style="font-weight: bold;"><?= htmlspecialchars($f['severity']) ?></span>
            <?php if (!empty($f['trigger']) && $f['trigger'] !== 'NONE'): ?>
                (Trigger: <?= htmlspecialchars($f['trigger']) ?>)
            <?php endif; ?>
        </div>
        <?php if (!empty($f['reason'])): ?>
            <div style="margin-top: 2pt;"><strong>Deficiency / Reason:</strong> <?= htmlspecialchars($f['reason']) ?></div>
        <?php endif; ?>
        <div style="margin-top: 2pt; color: #64748b;">
            <?php if (!empty($f['responsibleParty'])): ?>
                Responsible Party: <strong><?= htmlspecialchars($f['responsibleParty']) ?></strong> |
            <?php endif; ?>
            <?php if (!empty($f['targetCureDate'])): ?>
                Target Cure Date: <strong><?= DateService::format($f['targetCureDate'], 'M j, Y') ?></strong>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Detailed Question Breakdown -->
    <div class="page-break"></div>
    <div class="section-title">Itemized Assessment Responses</div>

    <?php foreach ($phaseResults as $p): 
        $qs = $questionsByPhase[$p['id']] ?? [];
        if (empty($qs)) continue;
    ?>
        <div style="font-weight: bold; font-size: 8.5pt; color: #0d1f3c; margin-top: 8pt; margin-bottom: 4pt; border-left: 3pt solid #c9a84c; padding-left: 4pt;">
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
                    $lightClass = ($light === 'GREEN') ? 'status-light-green' : (($light === 'AMBER') ? 'status-light-amber' : (($light === 'RED') ? 'status-light-red' : ''));
                    $ansDisplay = is_array($q['answer_value'] ?? '') ? 'Checklist' : (string)($q['answer_value'] ?? '—');
                    if (strlen($ansDisplay) > 25) {
                        $ansDisplay = substr($ansDisplay, 0, 22) . '...';
                    }
                ?>
                <tr>
                    <td><strong><?= $q['question_number'] ?></strong></td>
                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                    <td style="color: #64748b;"><?= $q['owner'] ?></td>
                    <td><?= htmlspecialchars($ansDisplay) ?></td>
                    <td><span class="<?= $lightClass ?>"><?= $light ?></span></td>
                    <td style="text-align: right;"><?= isset($q['score']) ? number_format((float)$q['score'], 0) : '—' ?>/10</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <!-- Signature & Authority Block -->
    <div style="margin-top: 20pt; padding-top: 10pt; border-top: 1pt solid #cbd5e1;">
        <table style="width: 100%; border-collapse: collapse; font-size: 8pt;">
            <tr>
                <td style="width: 50%; padding-right: 20pt;">
                    <div style="border-bottom: 1pt solid #64748b; height: 28pt;"></div>
                    <div style="margin-top: 4pt; color: #475569;">
                        <strong>Prepared By:</strong> <?= $assessorName ?><br>
                        Assessor Signature / Date
                    </div>
                </td>
                <td style="width: 50%; padding-left: 20pt;">
                    <div style="border-bottom: 1pt solid #64748b; height: 28pt;"></div>
                    <div style="margin-top: 4pt; color: #475569;">
                        <strong>Executive Review:</strong> United Five Construction, Inc.<br>
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

</body>
</html>
<?php
    return ob_get_clean();
}
