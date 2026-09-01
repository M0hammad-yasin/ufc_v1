<?php
/**
 * api/export_pdf.php
 * ─────────────────────────────────────────────────────────────────────────────
 * GET /ufc_v1/api/export_pdf.php?id=123
 * Generates and streams a high quality PDF report using Dompdf.
 */

$root = dirname(__DIR__);
require_once $root . '/includes/auth.php';
requireLogin();

require_once $root . '/config/database.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/DateService.php';
require_once $root . '/components/report-pdf-template.php';

function pdfFail(int $code, string $msg): void
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
    pdfFail(500, 'Dompdf library not found in vendor/. Please ensure vendor/dompdf is present.');
}

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    pdfFail(400, 'Invalid assessment ID');
}

$assessment = getAssessmentDetails($id);
if (!$assessment) {
    pdfFail(404, 'Assessment not found');
}

// 2. Generate Report HTML
try {
    $html = generateAssessmentPdfHtml($id);
} catch (\Throwable $e) {
    pdfFail(500, 'Failed to generate report markup: ' . $e->getMessage());
}

// 3. Render PDF via Dompdf
try {
    $options = new Options();
    $options->set('isPhpEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $output = $dompdf->output();
} catch (\Throwable $e) {
    pdfFail(500, 'PDF generation failed: ' . $e->getMessage());
}

$safeProjectName = preg_replace('/[^A-Za-z0-9_-]/', '_', $assessment['project_name'] ?? $assessment['client_name'] ?? 'Assessment');
$filename = 'UFC_Assessment_' . $id . '_' . $safeProjectName . '_' . DateService::todayUtc() . '.pdf';

// 4. Stream PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($output));
echo $output;
exit;
