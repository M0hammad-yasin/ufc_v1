<?php
/**
 * api/export_lead_pdf.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Generates and streams an official Lead Generation & Phase Summary PDF using Dompdf.
 * Supports choice of phases (All Phases 1 to 4, or specific Phase 1..4), 
 * plus options to include phase results and itemized questions & answers.
 */

$root = dirname(__DIR__);
require_once $root . '/includes/auth.php';
requireLogin();

require_once $root . '/config/database.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/DateService.php';
require_once $root . '/includes/EmailService.php';

function leadPdfFail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// 1. Locate Dompdf Autoloader
$composerAutoload = $root . '/vendor/autoload.php';
$manualAutoload   = $root . '/vendor/dompdf/autoload.inc.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} elseif (file_exists($manualAutoload)) {
    require_once $manualAutoload;
} else {
    leadPdfFail(500, 'Dompdf library not found in vendor/.');
}

use Dompdf\Dompdf;
use Dompdf\Options;

$assessmentId   = (int)($_GET['id'] ?? 0);
$phaseChoice    = trim((string)($_GET['phase'] ?? 'all'));
$includeResults = !isset($_GET['results']) || $_GET['results'] == '1';
$includeAnswers = !isset($_GET['answers']) || $_GET['answers'] == '1';
$attachment     = (isset($_GET['download']) && $_GET['download'] == '1') ? 1 : 0;

if ($assessmentId <= 0) {
    leadPdfFail(400, 'Invalid assessment ID');
}

$data = EmailService::getLeadSummaryData($assessmentId, $phaseChoice, $includeResults, $includeAnswers);
if (!$data) {
    leadPdfFail(404, 'Assessment not found');
}

$assessment   = $data['assessment'];
$phaseData    = $data['phase_data'];
$phaseLabel   = ($data['phase_choice'] === 'all') ? 'All Phases (1–4)' : 'Phase ' . implode(', ', $data['phase_numbers']);
$clientName   = htmlspecialchars($assessment['client_name'] ?? '—');
$clientEmail  = htmlspecialchars($assessment['client_email'] ?? '—');
$clientPhone  = htmlspecialchars($assessment['client_phone'] ?? '—');
$projectName  = htmlspecialchars($assessment['project_name'] ?? $clientName);
$projectAddress = htmlspecialchars($assessment['project_address'] ?? '—');
$projectType  = htmlspecialchars($assessment['project_type'] ?? '—');
$budget       = !empty($assessment['estimated_budget']) ? '$' . number_format((float)$assessment['estimated_budget'], 2) : '—';
$assessmentNo = htmlspecialchars($assessment['assessment_number'] ?? ('UFC-' . $assessmentId));
$status       = htmlspecialchars(str_replace('_', ' ', $assessment['status'] ?? 'IN PROGRESS'));
$assessor     = htmlspecialchars($assessment['assessor_name'] ?? 'Staff Assessor');
$dateIssued   = date('F j, Y');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lead Qualification Summary - <?= $assessmentNo ?></title>
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
            font-size: 9pt;
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
        }
        .brand-sub {
            font-size: 8pt;
            color: #475569;
            margin: 1pt 0;
        }
        .badge-title {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            font-size: 8pt;
            font-weight: bold;
            padding: 3pt 8pt;
            border-radius: 3pt;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4pt;
            margin-bottom: 14pt;
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
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
        }
        .phase-block {
            border: 1px solid #cbd5e1;
            border-radius: 4pt;
            margin-bottom: 12pt;
            page-break-inside: avoid;
            background-color: #ffffff;
        }
        .phase-title-bar {
            background-color: #0d1f3c;
            color: #ffffff;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 6pt 10pt;
            border-top-left-radius: 3pt;
            border-top-right-radius: 3pt;
        }
        .result-box {
            background-color: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 6pt 10pt;
            font-size: 8.5pt;
        }
        .status-pill {
            display: inline-block;
            padding: 2pt 6pt;
            font-size: 7.5pt;
            font-weight: bold;
            border-radius: 3pt;
            text-transform: uppercase;
        }
        .status-pass { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-hold { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .status-stop { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-blue { background-color: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        .question-card {
            border-bottom: 1px solid #f1f5f9;
            padding: 6pt 10pt;
            font-size: 8.5pt;
            page-break-inside: avoid;
        }
        .question-card:last-child {
            border-bottom: none;
        }
        .tag-light {
            display: inline-block;
            padding: 1pt 5pt;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 2pt;
            text-transform: uppercase;
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
    <!-- Header Letterhead -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 65%;">
                <div class="brand-title">UNITED FIVE CONSTRUCTION, INC.</div>
                <div class="brand-sub">General Contractor &middot; NYC DOB GC License #625679</div>
                <div class="brand-sub">1 World Trade Center, Suite 8500, New York, NY 10007</div>
            </td>
            <td style="vertical-align: top; width: 35%; text-align: right;">
                <div class="badge-title">LEAD QUALIFICATION SUMMARY</div>
                <div style="font-size: 8pt; color: #64748b; margin-top: 4pt;">Date: <?= $dateIssued ?></div>
                <div style="font-size: 8pt; color: #64748b;">Ref: <?= $assessmentNo ?></div>
                <div style="font-size: 8pt; color: #0d1f3c; font-weight: bold; margin-top: 2pt;"><?= htmlspecialchars($phaseLabel) ?></div>
            </td>
        </tr>
    </table>

    <!-- Lead & Client Metadata -->
    <table class="meta-table">
        <tr>
            <td style="width: 50%; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                <span class="meta-label">Client / Developer:</span>
                <span class="meta-value"><?= $clientName ?></span>
                <div style="color: #475569; font-size: 8pt;"><?= $clientEmail ?> &middot; <?= $clientPhone ?></div>
            </td>
            <td style="width: 50%; border-bottom: 1px solid #e2e8f0;">
                <span class="meta-label">Project Name &amp; Location:</span>
                <span class="meta-value"><?= $projectName ?></span>
                <div style="color: #475569; font-size: 8pt;"><?= $projectAddress ?></div>
            </td>
        </tr>
        <tr>
            <td style="border-right: 1px solid #e2e8f0;">
                <span class="meta-label">Project Scope &amp; Budget:</span>
                <span class="meta-value"><?= $projectType ?> &middot; <?= $budget ?></span>
            </td>
            <td>
                <span class="meta-label">Assessment Status &amp; Assessor:</span>
                <span class="meta-value"><?= $status ?> &middot; <?= $assessor ?></span>
            </td>
        </tr>
    </table>

    <!-- Phase Details -->
    <?php foreach ($phaseData as $pBlock): 
        $p = $pBlock['phase'];
        $r = $pBlock['result'];
        $questions = $pBlock['questions'];
    ?>
        <div class="phase-block">
            <div class="phase-title-bar">
                Phase <?= $p['phase_number'] ?>: <?= htmlspecialchars($p['title']) ?>
            </div>

            <!-- Phase Results -->
            <?php if ($includeResults): ?>
                <?php if ($r): 
                    $pillClass = match($r['status']) {
                        'PASS'      => 'status-pass',
                        'FAIL_HOLD' => 'status-hold',
                        'FAIL_STOP' => 'status-stop',
                        default     => 'status-blue',
                    };
                ?>
                    <div class="result-box">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: 30%;">
                                    <strong>Status:</strong> 
                                    <span class="status-pill <?= $pillClass ?>"><?= htmlspecialchars($r['status']) ?></span>
                                </td>
                                <td style="width: 40%;">
                                    <strong>Score:</strong> 
                                    <?= number_format((float)$r['score_earned'], 2) ?> / <?= number_format((float)$r['score_possible'], 2) ?> 
                                    (<?= number_format((float)$r['score_percent'], 1) ?>%)
                                </td>
                                <td style="width: 30%; text-align: right;">
                                    <?php if ((int)$r['red_count'] > 0): ?>
                                        <span style="color: #dc2626; font-weight: bold; margin-right: 6pt;">● <?= (int)$r['red_count'] ?> RED</span>
                                    <?php endif; ?>
                                    <?php if ((int)$r['amber_count'] > 0): ?>
                                        <span style="color: #d97706; font-weight: bold;">● <?= (int)$r['amber_count'] ?> AMBER</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="result-box" style="color: #64748b; font-style: italic;">
                        Phase evaluation not yet recorded.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Questions & Answers -->
            <?php if ($includeAnswers): ?>
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $q): 
                        $light = strtoupper($q['status_light'] ?? 'GREEN');
                        $lightPill = match($light) {
                            'RED'   => 'status-stop',
                            'AMBER' => 'status-hold',
                            default => 'status-pass',
                        };
                    ?>
                        <div class="question-card">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="vertical-align: top; width: 85%;">
                                        <span style="font-family: monospace; font-weight: bold; color: #475569;">[Q<?= htmlspecialchars($q['question_number']) ?>]</span>
                                        <strong><?= htmlspecialchars($q['question_text']) ?></strong>
                                    </td>
                                    <td style="vertical-align: top; width: 15%; text-align: right;">
                                        <span class="tag-light <?= $lightPill ?>"><?= $light ?></span>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top: 3pt; color: #334155; font-size: 8pt;">
                                <span style="color: #64748b;">Answer:</span> 
                                <strong><?= htmlspecialchars($q['answer_value'] ?: 'Not Answered / Skipped') ?></strong>
                                <?php if ($q['score'] !== null): ?>
                                    <span style="color: #64748b; margin-left: 6pt;">(Score: <?= number_format((float)$q['score'], 2) ?> / <?= number_format((float)$q['points_possible'], 2) ?>)</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($q['explain_reason'])): ?>
                                <div style="margin-top: 3pt; background-color: #fffbeb; border-left: 3px solid #d97706; padding: 3pt 6pt; font-size: 7.5pt; color: #92400e;">
                                    <strong>Deficiency:</strong> <?= htmlspecialchars($q['explain_reason']) ?>
                                    <?php if (!empty($q['target_cure_date'])): ?>
                                        &middot; Cure Date: <strong><?= htmlspecialchars($q['target_cure_date']) ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="question-card" style="color: #64748b; font-style: italic; font-size: 8pt;">
                        No questions answered in this phase yet.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Footer -->
    <table class="footer-table">
        <tr>
            <td style="width: 70%;">
                <div style="font-weight: bold; color: #0d1f3c;">United Five Construction, Inc.</div>
                <div>Lead Qualification &middot; 1 World Trade Center, Suite 8500, New York, NY 10007</div>
            </td>
            <td style="width: 30%; text-align: right; font-family: monospace; font-size: 7.5pt;">
                Document Form UFC-LEAD-v5<br>
                <?= date('Y-m-d H:i') ?> UTC
            </td>
        </tr>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->setPaper('letter', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$safeProjectName = preg_replace('/[^A-Za-z0-9_-]/', '_', $assessment['project_name'] ?? $assessment['client_name'] ?? 'Lead');
$safePhaseSuffix = ($phaseChoice === 'all') ? 'AllPhases' : "Phase{$phaseChoice}";
$filename = 'UFC_Lead_Summary_' . $assessmentId . '_' . $safeProjectName . '_' . $safePhaseSuffix . '.pdf';

$disposition = $attachment ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $dompdf->output();
exit;
