<?php

/**
 * United Five Construction - Client Pre-Assessment Final Report
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../components/report-body.php';

requireLogin();

$assessmentId = (int)($_GET['id'] ?? 0);
if (!$assessmentId) {
    die("Assessment ID missing.");
}

$assessment = getAssessmentDetails($assessmentId);
if (!$assessment) {
    die("Assessment not found.");
}

$pageTitle = "Assessment Report — " . ($assessment['project_name'] ?? $assessment['client_name'] ?? 'UFC');
require_once __DIR__ . '/../components/header.php';
?>

<div class="max-w-4xl mx-auto mt-6 space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 no-print bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-4 shadow-md">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/assessment.php?id=<?= $assessmentId ?>"
                class="px-3.5 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-colors flex items-center gap-2">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Return to Assessment</span>
            </a>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/assessment/preview-pdf.php?id=<?= $assessmentId ?>"
                target="_blank"
                class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 font-semibold text-xs rounded border border-[#1e3e68] transition-all flex items-center gap-2">
                <i class="fa-regular fa-eye text-blue-400"></i>
                <span>Preview PDF</span>
            </a>
            <a href="<?= BASE_URL ?>/api/export_pdf.php?id=<?= $assessmentId ?>"
                class="px-5 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-xs rounded-md shadow-lg transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-xs"></i>
                <span>Download PDF</span>
            </a>
            <button onclick="window.print()"
                class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 font-semibold text-xs rounded border border-[#1e3e68] transition-all flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-print text-xs"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <!-- Reusable Report Body -->
    <?= renderReportBody($assessmentId, false) ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>