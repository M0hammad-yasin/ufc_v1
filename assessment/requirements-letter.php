<?php

/**
 * United Five Construction - Client Requirements Letter
 * Redesigned per specification:
 * - Grouped by status: RED items, AMBER items, and PASSED questions
 * - Filtered on first 3 client-facing phases (Phase 4 internal excluded)
 * - Question-level deadline shown on top-left of each question card with high visibility
 * - "given date" text replacement
 * - Server-side PDF generation using Dompdf
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/letters.php';

requireLogin();

$assessmentId = (int)($_GET['id'] ?? 0);
if ($assessmentId <= 0) {
    header('Location: ' . BASE_URL . '/admin/assessments.php');
    exit;
}

$selectedPhase = isset($_GET['phase']) && $_GET['phase'] !== '' && $_GET['phase'] !== 'all' ? (int)$_GET['phase'] : null;
if ($selectedPhase !== null && $selectedPhase < 1) {
    $selectedPhase = null;
}

// Fetch requirements data grouped by status across client-facing phases (1-3)
$letterData = generateRequirementsLetterData($assessmentId, $selectedPhase);
$assessment = $letterData['assessment'];
$groupedByStatus = $letterData['grouped_by_status'];
$counts = $letterData['counts'];

// Check if PDF export is requested via Dompdf
$isPdfExport = (isset($_GET['export']) && $_GET['export'] === 'pdf') || (isset($_GET['format']) && $_GET['format'] === 'pdf');
if ($isPdfExport) {
    $root = dirname(__DIR__);
    $composerAutoload = $root . '/vendor/autoload.php';
    $manualAutoload   = $root . '/vendor/dompdf/autoload.inc.php';

    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } elseif (file_exists($manualAutoload)) {
        require_once $manualAutoload;
    } else {
        die('Dompdf library not found in vendor/.');
    }

    $html = generateRequirementsLetterPdfHtml($letterData, $selectedPhase);

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $assessment['project_name'] ?? $assessment['client_name'] ?? 'Assessment');
    $phaseSuffix = $selectedPhase ? "_Phase{$selectedPhase}" : "_AllClientFacingPhases";
    $filename = 'UFC_Requirements_Letter_' . $assessmentId . '_' . $safeName . $phaseSuffix . '.pdf';

    $attachment = (isset($_GET['download']) && $_GET['download'] == '1') ? 1 : 0;
    $dompdf->stream($filename, ['Attachment' => $attachment]);
    exit;
}

$pageTitle = "Requirements Letter — " . ($assessment['client_name'] ?? 'Client') . " — " . ($selectedPhase ? "Phase {$selectedPhase}" : "All Client-Facing Phases");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-cinzel {
            font-family: 'Cinzel', serif;
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
                font-size: 10pt;
            }

            .no-print {
                display: none !important;
            }

            .print-border {
                border-color: #cbd5e1 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen py-8 px-3 sm:px-6 antialiased font-sans">

    <!-- Action Toolbar (No Print) -->
    <div class="max-w-5xl mx-auto mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-slate-900/90 border border-slate-800 rounded-xl p-3 shadow-xl backdrop-blur no-print">
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/assessment.php?id=<?= $assessmentId ?>" class="text-xs text-slate-300 hover:text-white flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 px-3.5 py-2 rounded-lg border border-slate-700 transition-colors">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Return to Assessment</span>
            </a>
        </div>

        <!-- Phase Filtration Buttons (All Client-Facing Phases) -->
        <div class="flex items-center gap-1 bg-slate-950/70 p-1 rounded-lg border border-slate-800 self-center">
            <a href="?id=<?= $assessmentId ?>&phase=all" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all <?= ($selectedPhase === null) ? 'bg-[#c9a84c] text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' ?>">
                All Phases
            </a>
            <a href="?id=<?= $assessmentId ?>&phase=1" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all <?= ($selectedPhase === 1) ? 'bg-[#c9a84c] text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' ?>">
                Phase 1
            </a>
            <a href="?id=<?= $assessmentId ?>&phase=2" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all <?= ($selectedPhase === 2) ? 'bg-[#c9a84c] text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' ?>">
                Phase 2
            </a>
            <a href="?id=<?= $assessmentId ?>&phase=3" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all <?= ($selectedPhase === 3) ? 'bg-[#c9a84c] text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' ?>">
                Phase 3
            </a>
            <a href="?id=<?= $assessmentId ?>&phase=4" class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all <?= ($selectedPhase === 4) ? 'bg-[#c9a84c] text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' ?>">
                Phase 4
            </a>
        </div>

        <!-- Dompdf Actions -->
        <div class="flex items-center gap-2">
            <a href="?id=<?= $assessmentId ?><?= $selectedPhase ? '&phase=' . $selectedPhase : '' ?>&export=pdf&download=1"
                title="Download standalone PDF document generated by Dompdf"
                class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Download PDF</span>
            </a>
            <a href="?id=<?= $assessmentId ?><?= $selectedPhase ? '&phase=' . $selectedPhase : '' ?>&export=pdf"
                target="_blank"
                title="Open print-ready PDF compiled via Dompdf in new tab"
                class="px-4 py-2 bg-gradient-to-r from-[#c9a84c] to-[#d6b85e] hover:from-[#d6b85e] hover:to-[#e2c773] text-slate-950 font-bold text-xs rounded-lg shadow-md transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print PDF (Dompdf)</span>
            </a>
        </div>
    </div>

    <!-- Official Letter Document Card -->
    <div class="max-w-5xl mx-auto bg-white text-slate-900 rounded-2xl shadow-2xl p-6 sm:p-12 lg:p-14 border border-slate-200 font-sans print-border">

        <!-- Header / Letterhead -->
        <div class="border-b-2 border-[#0d1f3c] pb-6 mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-cinzel text-2xl sm:text-3xl font-bold text-[#0d1f3c] tracking-tight">
                    UNITED FIVE CONSTRUCTION, INC.
                </h1>
                <p class="text-xs text-slate-600 font-semibold mt-1">
                    General Contractor &middot; NYC DOB GC License #625679
                </p>
                <p class="text-xs text-slate-500">
                    1 World Trade Center, Suite 8500, New York, NY 10007
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="inline-block px-3 py-1 bg-amber-50 text-amber-900 border border-amber-300 text-xs font-bold rounded tracking-wide shadow-sm">
                    REQUIREMENTS NOTICE
                </span>
                <div class="text-xs text-slate-500 font-mono mt-1.5">Date: <?= date('F j, Y') ?></div>
                <div class="text-xs text-slate-600 font-mono font-semibold">Ref: <?= htmlspecialchars($assessment['assessment_number']) ?></div>
                <div class="text-xs text-[#0d1f3c] font-bold mt-0.5">
                    <?= $selectedPhase ? "Phase {$selectedPhase} Requirements" : "All Client-Facing Phases" ?>
                </div>
            </div>
        </div>

        <!-- Recipient & Project Details Card -->
        <div class="mb-8 p-4 sm:p-5 bg-slate-50 rounded-xl border border-slate-200 text-xs grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
                <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Client / Developer</span>
                <div class="font-bold text-slate-900 text-base"><?= htmlspecialchars($assessment['client_name']) ?></div>
                <div class="text-slate-600 font-medium"><?= htmlspecialchars($assessment['client_email']) ?></div>
                <?php if (!empty($assessment['client_phone'])): ?>
                    <div class="text-slate-500 text-[11px]"><?= htmlspecialchars($assessment['client_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="space-y-1">
                <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">Project Location &amp; Scope</span>
                <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($assessment['project_address'] ?: 'Address on file') ?></div>
                <div class="text-slate-600">Scope: <span class="font-medium text-slate-800"><?= htmlspecialchars($assessment['project_type'] ?? 'General Scope') ?></span></div>
                <?php if (!empty($assessment['project_name'])): ?>
                    <div class="text-slate-500 text-[11px]">Project: <?= htmlspecialchars($assessment['project_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formal Cover Notice Language (Required: "given date") -->
        <div class="space-y-3.5 text-sm text-slate-700 leading-relaxed mb-8">
            <p class="font-bold text-slate-900 text-base">
                Thank you for the opportunity to review your project.
            </p>
            <p>
                Before United Five Construction issues an estimate, we complete a structured readiness review of the project documentation, the property and the funding. We do this on every project, without exception. It protects you from receiving a number that changes later, and it protects us from committing crew and material to a project that is not yet ready to build.
            </p>
            <p>
                The items below are grouped by readiness status (<strong>Critical RED</strong>, <strong>Conditional AMBER</strong>, and <strong>Satisfied PASSED</strong>). Each question indicates its designated deadline at the top left with clear visibility. We are not able to produce a firm estimate while critical items remain open.
            </p>
            <p>
                <strong>This is not a decline.</strong> Send us these items and we will complete the review and move directly to pricing. We are glad to walk through the list with you and your design professional on a call.
            </p>
            <div class="p-3.5 bg-amber-50 border-l-4 border-amber-500 text-amber-900 font-medium rounded-r-lg shadow-sm">
                If we have not heard from you by <strong class="font-bold underline">given date</strong>, we will close the file. You are welcome to reopen it at any time.
            </div>
        </div>

        <!-- Executive Summary KPI Counters -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-8">
            <div class="p-3.5 rounded-xl border border-red-200 bg-red-50/60 flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold text-red-700 uppercase tracking-wider">Critical (RED)</div>
                    <div class="text-xs text-red-600 mt-0.5">Mandatory Stop / Hold items</div>
                </div>
                <span class="text-2xl font-black text-red-600"><?= $counts['red'] ?></span>
            </div>
            <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/60 flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold text-amber-800 uppercase tracking-wider">Conditional (AMBER)</div>
                    <div class="text-xs text-amber-700 mt-0.5">Deficiencies requiring cure</div>
                </div>
                <span class="text-2xl font-black text-amber-700"><?= $counts['amber'] ?></span>
            </div>
            <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/60 flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Compliant (PASSED)</div>
                    <div class="text-xs text-emerald-700 mt-0.5">Satisfied requirements</div>
                </div>
                <span class="text-2xl font-black text-emerald-700"><?= $counts['passed'] ?></span>
            </div>
        </div>

        <!-- Grouped Sections by Status -->
        <div class="space-y-8 mb-10">

            <!-- 1. RED ITEMS -->
            <?php $redGroup = $groupedByStatus['RED']; ?>
            <div class="border border-red-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-red-50 px-5 py-3 border-b border-red-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-red-500 inline-block shadow-sm"></span>
                        <h3 class="font-bold text-red-950 text-sm tracking-wide uppercase">
                            <?= htmlspecialchars($redGroup['title']) ?>
                        </h3>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-red-200 text-red-900">
                        <?= $redGroup['count'] ?> Item(s)
                    </span>
                </div>

                <?php if (empty($redGroup['items'])): ?>
                    <div class="p-5 text-xs text-slate-500 italic bg-white">
                        ✓ No critical RED deficiencies identified.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($redGroup['items'] as $item): ?>
                            <div class="p-5 text-xs space-y-3 hover:bg-slate-50/50 transition-colors">
                                <!-- Top Bar: DEADLINE ON TOP LEFT WITH CLEAR VISIBILITY -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-100/90 text-red-900 border-2 border-red-300 rounded-md font-bold text-xs tracking-wide shadow-sm">
                                            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span>DEADLINE: <?= htmlspecialchars($item['deadline_formatted'] ?: 'Within 30 Days') ?></span>
                                            <?php if ($item['days_remaining'] !== null): ?>
                                                <span class="ml-1 text-[10px] font-bold px-2 py-0.5 rounded <?= $item['is_overdue'] ? 'bg-red-600 text-white' : 'bg-red-200 text-red-900' ?>">
                                                    <?= $item['is_overdue'] ? 'OVERDUE' : ($item['days_remaining'] . ' days left') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-red-100 text-red-800 border border-red-300">
                                            RED LIGHT
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            Phase <?= $item['phase_number'] ?>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-semibold bg-[#1a3a5c]/10 text-[#0d1f3c] border border-[#1e3e68]/30">
                                            Responsible: <?= htmlspecialchars($item['responsible_party']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Question Title -->
                                <div class="font-bold text-slate-900 text-sm leading-snug">
                                    <span class="text-slate-500 mr-2 font-mono text-xs">Q<?= htmlspecialchars($item['question_number']) ?></span>
                                    <?= htmlspecialchars($item['question_text']) ?>
                                </div>

                                <!-- Guidance / Client Message -->
                                <?php if (!empty($item['client_message'])): ?>
                                    <div class="text-slate-700 bg-slate-50 p-3 rounded-lg border-l-4 border-red-400 border border-slate-200 leading-relaxed text-xs">
                                        <span class="font-bold text-slate-900 block mb-0.5">UFC Requirement:</span>
                                        <?= htmlspecialchars($item['client_message']) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Assessment Note / Reason -->
                                <?php if (!empty($item['reason'])): ?>
                                    <div class="text-slate-700 text-[11px] bg-red-50/40 p-2.5 rounded border border-red-100">
                                        <span class="font-bold text-red-900">Current Deficiency / Reason:</span> <?= htmlspecialchars($item['reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. AMBER ITEMS -->
            <?php $amberGroup = $groupedByStatus['AMBER']; ?>
            <div class="border border-amber-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-amber-50 px-5 py-3 border-b border-amber-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500 inline-block shadow-sm"></span>
                        <h3 class="font-bold text-amber-950 text-sm tracking-wide uppercase">
                            <?= htmlspecialchars($amberGroup['title']) ?>
                        </h3>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-amber-200 text-amber-900">
                        <?= $amberGroup['count'] ?> Item(s)
                    </span>
                </div>

                <?php if (empty($amberGroup['items'])): ?>
                    <div class="p-5 text-xs text-slate-500 italic bg-white">
                        ✓ No conditional AMBER deficiencies identified.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($amberGroup['items'] as $item): ?>
                            <div class="p-5 text-xs space-y-3 hover:bg-slate-50/50 transition-colors">
                                <!-- Top Bar: DEADLINE ON TOP LEFT WITH CLEAR VISIBILITY -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100/90 text-amber-900 border-2 border-amber-300 rounded-md font-bold text-xs tracking-wide shadow-sm">
                                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span>DEADLINE: <?= htmlspecialchars($item['deadline_formatted'] ?: 'Within 30 Days') ?></span>
                                            <?php if ($item['days_remaining'] !== null): ?>
                                                <span class="ml-1 text-[10px] font-bold px-2 py-0.5 rounded <?= $item['is_overdue'] ? 'bg-red-600 text-white' : 'bg-amber-200 text-amber-900' ?>">
                                                    <?= $item['is_overdue'] ? 'OVERDUE' : ($item['days_remaining'] . ' days left') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-100 text-amber-800 border border-amber-300">
                                            AMBER LIGHT
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            Phase <?= $item['phase_number'] ?>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-semibold bg-[#1a3a5c]/10 text-[#0d1f3c] border border-[#1e3e68]/30">
                                            Responsible: <?= htmlspecialchars($item['responsible_party']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Question Title -->
                                <div class="font-bold text-slate-900 text-sm leading-snug">
                                    <span class="text-slate-500 mr-2 font-mono text-xs">Q<?= htmlspecialchars($item['question_number']) ?></span>
                                    <?= htmlspecialchars($item['question_text']) ?>
                                </div>

                                <!-- Guidance / Client Message -->
                                <?php if (!empty($item['client_message'])): ?>
                                    <div class="text-slate-700 bg-slate-50 p-3 rounded-lg border-l-4 border-amber-400 border border-slate-200 leading-relaxed text-xs">
                                        <span class="font-bold text-slate-900 block mb-0.5">UFC Requirement:</span>
                                        <?= htmlspecialchars($item['client_message']) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Assessment Note / Reason -->
                                <?php if (!empty($item['reason'])): ?>
                                    <div class="text-slate-700 text-[11px] bg-amber-50/40 p-2.5 rounded border border-amber-100">
                                        <span class="font-bold text-amber-900">Current Deficiency / Reason:</span> <?= htmlspecialchars($item['reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. PASSED QUESTIONS -->
            <?php $passedGroup = $groupedByStatus['PASSED']; ?>
            <div class="border border-emerald-200 rounded-xl overflow-hidden shadow-sm">
                <div class="bg-emerald-50 px-5 py-3 border-b border-emerald-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block shadow-sm"></span>
                        <h3 class="font-bold text-emerald-950 text-sm tracking-wide uppercase">
                            <?= htmlspecialchars($passedGroup['title']) ?>
                        </h3>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-200 text-emerald-900">
                        <?= $passedGroup['count'] ?> Item(s)
                    </span>
                </div>

                <?php if (empty($passedGroup['items'])): ?>
                    <div class="p-5 text-xs text-slate-500 italic bg-white">
                        No items marked passed in this selection.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($passedGroup['items'] as $item): ?>
                            <div class="p-4 sm:p-5 text-xs space-y-2.5 hover:bg-slate-50/50 transition-colors">
                                <!-- Top Bar: DEADLINE ON TOP LEFT WITH CLEAR VISIBILITY -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-900 border border-emerald-300 rounded-md font-bold text-xs tracking-wide">
                                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>PASSED &mdash; NO ACTION REQUIRED</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            PASS
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            Phase <?= $item['phase_number'] ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                            Responsible: <?= htmlspecialchars($item['responsible_party']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Question Title -->
                                <div class="font-semibold text-slate-900 text-sm leading-snug">
                                    <span class="text-slate-500 mr-2 font-mono text-xs">Q<?= htmlspecialchars($item['question_number']) ?></span>
                                    <?= htmlspecialchars($item['question_text']) ?>
                                </div>

                                <!-- Compliant Answer Recorded -->
                                <?php if (!empty($item['answer_value'])): ?>
                                    <div class="text-[11px] text-emerald-800 bg-emerald-50/70 px-3 py-1.5 rounded border border-emerald-100 flex items-center gap-2">
                                        <span class="font-bold text-emerald-900">Compliant Response:</span>
                                        <span><?= htmlspecialchars($item['answer_value']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Closing Sign-off -->
        <div class="pt-8 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 text-xs text-slate-600">
            <div class="space-y-1">
                <p class="font-cinzel font-bold text-slate-900 text-base">United Five Construction, Inc.</p>
                <p class="text-slate-600">Pre-Assessment Qualification Division</p>
                <p class="text-[11px] text-slate-500">1 World Trade Center, Suite 8500, New York, NY 10007</p>
                <p class="mt-2 text-slate-700 font-medium">Deadlines: <strong class="text-slate-900">Refer to individual item dates specified above</strong></p>
            </div>
            <div class="text-left sm:text-right text-[11px] text-slate-400 font-mono space-y-0.5">
                <div>Document Form UFC-REQ-v5</div>
                <div>Generated: <?= date('F j, Y') ?></div>
            </div>
        </div>

    </div>

</body>

</html>