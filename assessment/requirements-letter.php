<?php
/**
 * United Five Construction - Client Requirements Letter
 * Implements exact v5.0 PDF specification
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/letters.php';

requireLogin();

$assessmentId = (int)($_GET['id'] ?? 0);
$phaseNumber = (int)($_GET['phase'] ?? 1);

$letterData = generateRequirementsLetterData($assessmentId, $phaseNumber);
$assessment = $letterData['assessment'];
$deadline = $letterData['deadline_formatted'];
$groupedItems = $letterData['grouped_items'];

$pageTitle = "Requirements Letter — {$assessment['client_name']} — Phase {$phaseNumber}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/ufc_v1/assets/css/style.css">
    <style>
        @media print {
            body { background: white !important; color: black !important; font-size: 11pt; }
            .no-print { display: none !important; }
            .print-border { border-color: #94a3b8 !important; }
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen py-8 px-4 sm:px-6">

    <!-- Action Toolbar (No Print) -->
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-print">
        <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" class="text-xs text-slate-300 hover:text-white flex items-center gap-1 bg-[#1a3a5c] px-3 py-1.5 rounded border border-[#1e3e68]">
            ← Return to Assessment
        </a>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-4 py-1.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-xs rounded shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print / Save PDF</span>
            </button>
        </div>
    </div>

    <!-- Official Letter Document -->
    <div class="max-w-4xl mx-auto bg-white text-slate-900 rounded-xl shadow-2xl p-8 sm:p-14 border border-slate-300 font-sans print-border">
        
        <!-- Header / Letterhead -->
        <div class="border-b-2 border-[#0d1f3c] pb-6 mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-serif text-2xl font-bold text-[#0d1f3c] tracking-tight">
                    UNITED FIVE CONSTRUCTION, INC.
                </h1>
                <p class="text-xs text-slate-600 font-medium mt-1">
                    General Contractor &middot; NYC DOB GC License #625679
                </p>
                <p class="text-xs text-slate-500">
                    1 World Trade Center, Suite 8500, New York, NY 10007
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="inline-block px-3 py-1 bg-amber-100 text-amber-900 border border-amber-300 text-xs font-bold rounded">
                    REQUIREMENTS LETTER
                </span>
                <div class="text-xs text-slate-500 font-mono mt-1">Date: <?= date('F j, Y') ?></div>
                <div class="text-xs text-slate-500 font-mono">Ref: <?= htmlspecialchars($assessment['assessment_number']) ?></div>
            </div>
        </div>

        <!-- Recipient Details -->
        <div class="mb-8 p-4 bg-slate-50 rounded border border-slate-200 text-xs grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <span class="text-slate-500 font-semibold block uppercase">Client / Developer:</span>
                <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($assessment['client_name']) ?></span>
                <span class="block text-slate-600"><?= htmlspecialchars($assessment['client_email']) ?></span>
            </div>
            <div>
                <span class="text-slate-500 font-semibold block uppercase">Project Location:</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($assessment['project_address']) ?></span>
                <span class="block text-slate-600">Scope: <?= htmlspecialchars($assessment['project_type'] ?? 'General Scope') ?></span>
            </div>
        </div>

        <!-- Letter Body Cover Language (Exact Spec) -->
        <div class="space-y-4 text-sm text-slate-700 leading-relaxed mb-8">
            <p class="font-semibold text-slate-900">
                Thank you for the opportunity to review your project.
            </p>
            <p>
                Before United Five Construction issues an estimate, we complete a structured readiness review of the project documentation, the property and the funding. We do this on every project, without exception. It protects you from receiving a number that changes later, and it protects us from committing crew and material to a project that is not yet ready to build.
            </p>
            <p>
                The items below are currently outstanding. Each is marked with the party who controls it. We are not able to produce a firm estimate while they remain open.
            </p>
            <p>
                <strong>This is not a decline.</strong> Send us these items and we will complete the review and move directly to pricing. We are glad to walk through the list with you and your design professional on a call.
            </p>
            <p class="p-3 bg-amber-50 border-l-4 border-amber-500 text-amber-900 font-medium">
                If we have not heard from you by <strong class="underline"><?= $deadline ?></strong>, we will close the file. You are welcome to reopen it at any time.
            </p>
        </div>

        <!-- Requirements Items Table Grouped by Responsible Party -->
        <div class="mb-10 space-y-6">
            <h3 class="font-serif text-lg font-bold text-[#0d1f3c] border-b pb-2">
                Phase <?= $phaseNumber ?> Outstanding Requirements
            </h3>

            <?php if (empty($groupedItems)): ?>
                <div class="p-4 bg-slate-100 rounded text-xs text-slate-600 italic">
                    No open client-facing requirements for this phase.
                </div>
            <?php else: ?>
                <?php foreach ($groupedItems as $party => $items): ?>
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <div class="bg-slate-100 px-4 py-2 text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-200 flex items-center justify-between">
                            <span>Responsible Party: <?= htmlspecialchars($party) ?></span>
                            <span class="text-[11px] font-normal text-slate-500"><?= count($items) ?> Item(s)</span>
                        </div>
                        <div class="divide-y divide-slate-200">
                            <?php foreach ($items as $item): 
                                $isRed = ($item['status_light'] === 'RED');
                            ?>
                            <div class="p-4 text-xs space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="font-bold text-slate-900 text-sm">
                                        <span class="text-slate-500 mr-2 font-mono">Q<?= $item['question_number'] ?></span>
                                        <?= htmlspecialchars($item['question_text']) ?>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0 <?= $isRed ? 'bg-red-100 text-red-800 border border-red-300' : 'bg-amber-100 text-amber-800 border border-amber-300' ?>">
                                        <?= $item['status_light'] ?>
                                    </span>
                                </div>

                                <?php if (!empty($item['client_message'])): ?>
                                    <div class="text-slate-600 bg-slate-50 p-2.5 rounded border border-slate-200 italic leading-relaxed">
                                        <?= htmlspecialchars($item['client_message']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['reason'])): ?>
                                    <div class="text-slate-700 text-[11px]">
                                        <span class="font-semibold text-slate-900">Current Status / Note:</span> <?= htmlspecialchars($item['reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Closing Sign-off -->
        <div class="pt-6 border-t border-slate-200 flex justify-between items-end text-xs text-slate-600">
            <div>
                <p class="font-serif font-bold text-slate-900 text-sm">United Five Construction, Inc.</p>
                <p>Pre-Assessment Qualification Division</p>
                <p class="mt-1 text-slate-500">Response Deadline: <strong><?= $deadline ?></strong></p>
            </div>
            <div class="text-right text-[11px] text-slate-400 font-mono">
                Document Form UFC-REQ-v5
            </div>
        </div>
    </div>
</body>
</html>
