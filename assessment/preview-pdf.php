<?php

/**
 * United Five Construction - Client Pre-Assessment PDF Document Preview
 * Dedicated preview page using standard web app header and footer,
 * rendering the exact same PDF template from single source of truth.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../components/report-pdf-template.php';

requireLogin();

$assessmentId = (int)($_GET['id'] ?? 0);
if (!$assessmentId) {
    die("Assessment ID missing.");
}

$assessment = getAssessmentDetails($assessmentId);
if (!$assessment) {
    die("Assessment not found.");
}

$pageTitle = "PDF Report Preview — " . ($assessment['project_name'] ?? $assessment['client_name'] ?? 'UFC');
require_once __DIR__ . '/../components/header.php';
?>

<div class="max-w-5xl mx-auto my-6">
    <!-- Top Action Toolbar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 no-print bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-4 shadow-md">
        <div class="flex items-center gap-3">
            <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>"
                class="px-3.5 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-colors flex items-center gap-2">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Back to Assessment</span>
            </a>
            <div class="text-xs text-slate-400">
                <span class="text-slate-200 font-bold">PDF Document Preview</span> &middot; <?= htmlspecialchars($assessment['assessment_number']) ?>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/ufc_v1/api/export_pdf.php?id=<?= $assessmentId ?>"
                class="px-5 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-xs rounded-md shadow-lg transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-xs"></i>
                <span>Download PDF</span>
            </a>
        </div>
    </div>

    <!-- Exact PDF Document Render (Single Source of Truth - Fixed Viewport Wrapper) -->
    <div class="my-6 overflow-x-auto w-full">
        <div class="w-[850px] mx-auto">
            <?= generateAssessmentPdfHtml($assessmentId, true) ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>