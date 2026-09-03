<?php

/**
 * United Five Construction - Client Decline Letter
 * Implements exact v5.0 PDF specification
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/letters.php';

requireLogin();

$assessmentId = (int)($_GET['id'] ?? 0);
$letterData = generateDeclineLetterData($assessmentId);
$assessment = $letterData['assessment'];
$reason = $letterData['decline_reason'];
$initialLetterDate = $letterData['initial_letter_date'];

$pageTitle = "Decline Letter — {$assessment['client_name']} — UFC";
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
            body {
                background: white !important;
                color: black !important;
                font-size: 11pt;
            }

            .no-print {
                display: none !important;
            }

            .print-border {
                border-color: #94a3b8 !important;
            }
        }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen py-8 px-4 sm:px-6">

    <!-- Action Toolbar (No Print) -->
    <div class="max-w-4xl mx-auto mt-6 mb-6 flex items-center justify-between no-print">
        <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" class="text-xs text-slate-300 hover:text-white flex items-center gap-1 bg-[#1a3a5c] px-3 py-1.5 rounded border border-[#1e3e68]">
            ← Return to Assessment
        </a>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="px-4 py-1.5 bg-[#8b0000] hover:bg-red-700 text-white font-bold text-xs rounded shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span>Print / Save PDF</span>
            </button>
        </div>
    </div>

    <!-- Official Decline Letter Document -->
    <div class="max-w-4xl mx-auto mt-6 bg-white text-slate-900 rounded-xl shadow-2xl p-8 sm:p-14 border border-slate-300 font-sans print-border">

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
                <span class="inline-block px-3 py-1 bg-red-100 text-red-900 border border-red-300 text-xs font-bold rounded">
                    QUALIFICATION NOTIFICATION
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
                <span class="text-slate-500 font-semibold block uppercase">Project Site:</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($assessment['project_address']) ?></span>
                <span class="block text-slate-600">Scope: <?= htmlspecialchars($assessment['project_type'] ?? 'General Scope') ?></span>
            </div>
        </div>

        <!-- Letter Body Cover Language (Exact Spec) -->
        <div class="space-y-5 text-sm text-slate-700 leading-relaxed mb-10">
            <p>
                Thank you for considering United Five Construction for your project.
            </p>
            <p>
                After completing our readiness review, we have concluded that we are not the right fit for this project at this time. The attached assessment shows what our review examined and where it stopped.
            </p>

            <!-- Dynamic Reason-Specific Paragraph -->
            <div class="p-4 rounded-lg bg-slate-50 border-l-4 border-[#8b0000] text-slate-800 font-medium">
                <?php if ($reason === 'UFC_CAPACITY'): ?>
                    This decision is not about your project or your documentation, both of which are in order. It reflects our current commitments and capacity. We would rather tell you now than take work we cannot staff properly.
                <?php elseif ($reason === 'UNRESPONSIVE'): ?>
                    We did not receive the items requested in our letter of <?= $initialLetterDate ?> and have closed the file accordingly.
                <?php else: // STOP_TRIGGER or other 
                ?>
                    The condition that prevents us from proceeding is identified in the assessment. It is not a judgment about you or your project. It is a legal or regulatory condition that must be resolved before any licensed contractor can lawfully perform this work.
                <?php endif; ?>
            </div>

            <p>
                We would welcome the opportunity to look at this again if circumstances change, and we are glad to recommend other qualified contractors if that would help.
            </p>
        </div>

        <!-- Closing Sign-off -->
        <div class="pt-8 border-t border-slate-200 flex justify-between items-end text-xs text-slate-600">
            <div>
                <p class="font-serif font-bold text-slate-900 text-sm">United Five Construction, Inc.</p>
                <p>Pre-Assessment & Estimating Department</p>
            </div>
            <div class="text-right text-[11px] text-slate-400 font-mono">
                Document Form UFC-DECLINE-v5
            </div>
        </div>
    </div>
</body>

</html>