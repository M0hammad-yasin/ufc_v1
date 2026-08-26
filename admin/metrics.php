<?php
/**
 * United Five Construction - Lead Conversion & Pipeline Qualification Metrics
 * PDF Specification Section 4 Page 16
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo = getDbConnection();

// Total Leads
$totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM assessments")->fetchColumn();

// Passed / Proceed to proposal
$passedCount = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE status = 'PROCEED_TO_PROPOSAL'")->fetchColumn();

// Declined / Not a fit
$declinedCount = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE status = 'NOT_A_FIT'")->fetchColumn();

// In progress
$inProgressCount = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE status = 'IN_PROGRESS'")->fetchColumn();

// Current Active Holds
$currentHoldCount = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE status = 'HOLD'")->fetchColumn();

// Total Assessments that were ever on HOLD (from history or current)
$everOnHoldCount = (int)$pdo->query("
    SELECT COUNT(DISTINCT assessment_id) 
    FROM (
        SELECT assessment_id FROM phase_results WHERE status = 'FAIL_HOLD'
        UNION
        SELECT id AS assessment_id FROM assessments WHERE status = 'HOLD'
    ) AS h
")->fetchColumn();

// Total Leads that were once on HOLD and successfully passed subsequent phases
$holdRecoveredCount = (int)$pdo->query("
    SELECT COUNT(DISTINCT pr.assessment_id) 
    FROM phase_results pr
    JOIN assessments a ON pr.assessment_id = a.id
    WHERE pr.status = 'FAIL_HOLD' AND a.status = 'PROCEED_TO_PROPOSAL'
")->fetchColumn();

// Rates
$conversionRate = $totalLeads > 0 ? round(($passedCount / $totalLeads) * 100, 1) : 0;
$holdReturnRate = $everOnHoldCount > 0 ? round(($holdRecoveredCount / $everOnHoldCount) * 100, 1) : 0;

$pageTitle = 'Conversion Metrics — UFC Pre-Assessment';
require_once __DIR__ . '/../components/header.php';
?>

<div class="space-y-8">
    <div>
        <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Qualification & Conversion Metrics</h1>
        <p class="text-xs text-slate-400 mt-1">
            Measures lead seriousness, pipeline throughput, and requirements return rate.
        </p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-md">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Leads Entered</div>
            <div class="text-3xl font-serif font-bold text-white mt-2"><?= $totalLeads ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Initiated at Phase 1</div>
        </div>

        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-emerald-600/50 shadow-md">
            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Proceed to Proposal</div>
            <div class="text-3xl font-serif font-bold text-emerald-400 mt-2"><?= $passedCount ?></div>
            <div class="text-[11px] text-slate-400 mt-1"><?= $conversionRate ?>% qualification rate</div>
        </div>

        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-amber-600/50 shadow-md">
            <div class="text-xs font-semibold uppercase tracking-wider text-[#c9a84c]">HOLD Return Rate</div>
            <div class="text-3xl font-serif font-bold text-[#c9a84c] mt-2"><?= $holdReturnRate ?>%</div>
            <div class="text-[11px] text-slate-400 mt-1"><?= $holdRecoveredCount ?> of <?= $everOnHoldCount ?> leads cured items</div>
        </div>

        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-red-600/50 shadow-md">
            <div class="text-xs font-semibold uppercase tracking-wider text-red-400">Filtered Out (Not A Fit)</div>
            <div class="text-3xl font-serif font-bold text-red-400 mt-2"><?= $declinedCount ?></div>
            <div class="text-[11px] text-slate-400 mt-1">Estimating hours saved</div>
        </div>
    </div>

    <!-- Metric Breakdown & Business Insights -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-md space-y-4">
            <h3 class="font-serif font-bold text-base text-slate-100 border-b border-[#1e3e68] pb-2">
                Pipeline Status Breakdown
            </h3>
            
            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-300">Active In Progress</span>
                    <span class="font-bold text-blue-400"><?= $inProgressCount ?></span>
                </div>
                <div class="w-full bg-[#060f1e] rounded-full h-1.5 overflow-hidden">
                    <div class="bg-blue-500 h-1.5" style="width: <?= $totalLeads > 0 ? ($inProgressCount / $totalLeads) * 100 : 0 ?>%"></div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-slate-300">Awaiting Client Response (HOLD)</span>
                    <span class="font-bold text-[#c9a84c]"><?= $currentHoldCount ?></span>
                </div>
                <div class="w-full bg-[#060f1e] rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#c9a84c] h-1.5" style="width: <?= $totalLeads > 0 ? ($currentHoldCount / $totalLeads) * 100 : 0 ?>%"></div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-slate-300">Proceed to Proposal (All 4 Passed)</span>
                    <span class="font-bold text-emerald-400"><?= $passedCount ?></span>
                </div>
                <div class="w-full bg-[#060f1e] rounded-full h-1.5 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5" style="width: <?= $totalLeads > 0 ? ($passedCount / $totalLeads) * 100 : 0 ?>%"></div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-slate-300">Declined / Unresponsive (NOT A FIT)</span>
                    <span class="font-bold text-red-400"><?= $declinedCount ?></span>
                </div>
                <div class="w-full bg-[#060f1e] rounded-full h-1.5 overflow-hidden">
                    <div class="bg-red-500 h-1.5" style="width: <?= $totalLeads > 0 ? ($declinedCount / $totalLeads) * 100 : 0 ?>%"></div>
                </div>
            </div>
        </div>

        <div class="p-6 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-md space-y-4">
            <h3 class="font-serif font-bold text-base text-slate-100 border-b border-[#1e3e68] pb-2">
                Strategic Intake Observation
            </h3>
            <p class="text-xs text-slate-300 leading-relaxed">
                As defined in the UFC Pre-Assessment specification, the <strong>Requirements Return Rate</strong> is the single clearest indicator of client seriousness.
            </p>
            <p class="text-xs text-slate-400 leading-relaxed">
                Clients who cure missing architectural drawings, provide proof of funds, and execute the Preconstruction Services Agreement convert at near 100% into active construction contracts, eliminating wasted estimating hours on unviable leads.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
