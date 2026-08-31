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

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Top Action Bar -->
    <div class="flex items-center justify-between no-print">
        <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
           class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-medium rounded-md border border-[#1e3e68] transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Return to Assessment</span>
        </a>
        <button onclick="window.print()" 
                class="px-5 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-sm rounded-md shadow-lg transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            <span>Print Report</span>
        </button>
    </div>

    <!-- Reusable Report Body -->
    <?= renderReportBody($assessmentId, false) ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
