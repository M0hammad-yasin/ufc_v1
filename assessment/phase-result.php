<?php
/**
 * United Five Construction - Phase Gatekeeper Evaluation Result View
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';

requireLogin();
$currentUser = getCurrentUser();

$assessmentId = (int)($_GET['id'] ?? 0);
$phaseNumber = (int)($_GET['phase'] ?? 1);

$assessment = getAssessmentDetails($assessmentId);
if (!$assessment) {
    die("Assessment not found.");
}

$phase = getPhaseByNumber($phaseNumber);
if (!$phase) {
    die("Phase not found.");
}

// Re-evaluate gate to get current freshest stats
$gate = evaluatePhaseGate($assessmentId, $phaseNumber, $currentUser['name']);

// Fetch answers and questions for detailed phase breakdown
$questions = getApplicableQuestionsForPhase($assessmentId, $phaseNumber);
$answersMap = getAssessmentAnswersMap($assessmentId);

$pageTitle = "Phase {$phaseNumber} Result — {$assessment['client_name']} — UFC";
require_once __DIR__ . '/../components/header.php';
$activePhaseNumber = $phaseNumber;
require_once __DIR__ . '/../components/phase-nav.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Result Card -->
    <?php if ($gate['status'] === 'PASS'): ?>
        <div class="bg-[#0d1f3c] border-2 border-emerald-500/80 rounded-xl p-8 sm:p-10 shadow-2xl mb-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-10">
                <svg class="w-36 h-36 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-950 text-emerald-300 border border-emerald-500">
                    GATE PASSED
                </span>
                <span class="text-xs text-slate-400 font-mono">Phase <?= $phaseNumber ?> Complete</span>
            </div>

            <h1 class="font-serif text-3xl font-bold text-white mb-2">
                <?= ($phaseNumber < 4) ? "Phase {$phaseNumber} Passed Successfully" : "All Four Phases Passed — PROCEED TO PROPOSAL" ?>
            </h1>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed mb-6">
                <?= htmlspecialchars($gate['message']) ?>
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-lg bg-[#060f1e]/80 border border-[#1e3e68] mb-8">
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Score Earned</div>
                    <div class="text-xl font-bold text-emerald-400"><?= $gate['score_earned'] ?> / <?= $gate['score_possible'] ?></div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Percentage</div>
                    <div class="text-xl font-bold text-emerald-400"><?= $gate['score_percent'] ?>%</div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">RED Items</div>
                    <div class="text-xl font-bold text-slate-200"><?= $gate['red_count'] ?></div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">AMBER Items</div>
                    <div class="text-xl font-bold text-[#c9a84c]"><?= $gate['amber_count'] ?></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <?php if ($phaseNumber < 4): ?>
                    <a href="/ufc_v1/assessment/question.php?id=<?= $assessmentId ?>&q=<?= ($phaseNumber + 1) . '.1' ?>" 
                       class="px-8 py-3 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-sm rounded-md shadow-lg transition-all flex items-center gap-2">
                        <span>Unlock & Begin Phase <?= $phaseNumber + 1 ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <div class="p-4 bg-emerald-950/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm font-semibold">
                        ✓ Release project to Estimating. Pre-assessment qualification complete.
                    </div>
                <?php endif; ?>

                <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
                   class="px-5 py-3 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-medium rounded-md border border-[#1e3e68] transition-colors">
                    Assessment Summary
                </a>
            </div>
        </div>

    <?php elseif ($gate['status'] === 'FAIL_HOLD'): ?>
        <div class="bg-[#0d1f3c] border-2 border-[#c9a84c] rounded-xl p-8 sm:p-10 shadow-2xl mb-8 relative">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-950 text-[#c9a84c] border border-amber-600">
                    HOLD — REQUIREMENTS OUTSTANDING
                </span>
                <span class="text-xs text-slate-400 font-mono">Phase <?= $phaseNumber ?> Gate</span>
            </div>

            <h1 class="font-serif text-3xl font-bold text-white mb-2">
                Phase <?= $phaseNumber ?> Qualification Paused
            </h1>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed mb-6">
                Curable deficiencies were identified. Later phases remain locked. A formal <strong>Phase <?= $phaseNumber ?> Requirements Letter</strong> has been generated with a 30-day response window ending <strong><?= date('F j, Y', strtotime('+30 days')) ?></strong>.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 rounded-lg bg-[#060f1e]/80 border border-[#1e3e68] mb-8">
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">RED Deficiency Items</div>
                    <div class="text-xl font-bold text-red-400"><?= $gate['red_count'] ?></div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">AMBER Items</div>
                    <div class="text-xl font-bold text-[#c9a84c]"><?= $gate['amber_count'] ?></div>
                </div>
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-slate-400">Response Deadline</div>
                    <div class="text-sm font-bold text-slate-200 mt-1"><?= date('M j, Y', strtotime('+30 days')) ?></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <a href="/ufc_v1/assessment/requirements-letter.php?id=<?= $assessmentId ?>&phase=<?= $phaseNumber ?>" 
                   target="_blank"
                   class="px-8 py-3 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-sm rounded-md shadow-lg transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>View / Print Requirements Letter</span>
                </a>

                <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
                   class="px-5 py-3 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-medium rounded-md border border-[#1e3e68] transition-colors">
                    Return to Assessment Details
                </a>
            </div>
        </div>

    <?php elseif ($gate['status'] === 'FAIL_STOP'): ?>
        <div class="bg-[#0d1f3c] border-2 border-red-500/80 rounded-xl p-8 sm:p-10 shadow-2xl mb-8 relative">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-950 text-red-300 border border-red-600">
                    NOT A FIT
                </span>
                <span class="text-xs text-slate-400 font-mono">Termination</span>
            </div>

            <h1 class="font-serif text-3xl font-bold text-white mb-2">
                Assessment Terminated — Not A Fit
            </h1>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed mb-6">
                <?= htmlspecialchars($gate['message']) ?>
            </p>

            <div class="flex flex-wrap items-center gap-4">
                <a href="/ufc_v1/assessment/decline-letter.php?id=<?= $assessmentId ?>" 
                   target="_blank"
                   class="px-8 py-3 bg-red-800 hover:bg-red-700 text-white font-bold text-sm rounded-md shadow-lg transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>View / Print Official Decline Letter</span>
                </a>

                <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
                   class="px-5 py-3 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-medium rounded-md border border-[#1e3e68] transition-colors">
                    Return to Assessment Details
                </a>
            </div>
        </div>

    <?php elseif ($gate['status'] === 'ESCALATED'): ?>
        <div class="bg-[#0d1f3c] border-2 border-purple-500/80 rounded-xl p-8 sm:p-10 shadow-2xl mb-8 relative">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-950 text-purple-300 border border-purple-600">
                    ESCALATED — AWAITING CEO
                </span>
            </div>

            <h1 class="font-serif text-3xl font-bold text-white mb-2">
                Escalated for Executive / Legal Review
            </h1>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed mb-6">
                Phase <?= $phaseNumber ?> cannot close until the Chief Executive Officer or legal counsel reviews and clears the escalation flag.
            </p>

            <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
               class="px-6 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-sm rounded shadow inline-block">
                View Executive Override Panel
            </a>
        </div>
    <?php endif; ?>

    <!-- Phase Breakdown Table -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-6 shadow-xl">
        <h3 class="font-serif font-bold text-lg text-slate-100 mb-4">
            Phase <?= $phaseNumber ?> Item Responses
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#1e3e68] text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Q#</th>
                        <th class="py-3 px-3">Question</th>
                        <th class="py-3 px-3">Owner</th>
                        <th class="py-3 px-3">Answer</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Score</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e3e68]">
                    <?php foreach ($questions as $q): 
                        $ans = $answersMap[$q['question_number']] ?? null;
                        $statusClass = 'badge-neutral';
                        if ($ans) {
                            if ($ans['status_light'] === 'GREEN') $statusClass = 'badge-green';
                            if ($ans['status_light'] === 'AMBER') $statusClass = 'badge-amber';
                            if ($ans['status_light'] === 'RED') $statusClass = 'badge-red';
                        }
                    ?>
                    <tr class="hover:bg-[#1a3a5c]/30 transition-colors">
                        <td class="py-3 px-3 font-mono font-bold text-[#c9a84c]"><?= $q['question_number'] ?></td>
                        <td class="py-3 px-3 font-medium text-slate-200 max-w-xs"><?= htmlspecialchars($q['question_text']) ?></td>
                        <td class="py-3 px-3 text-slate-400"><?= $q['owner'] ?></td>
                        <td class="py-3 px-3 font-semibold text-slate-200">
                            <?= htmlspecialchars(is_array($ans['answer_value'] ?? '') ? 'Multi-select' : (string)($ans['answer_value'] ?? '—')) ?>
                        </td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $statusClass ?>">
                                <?= $ans['status_light'] ?? 'PENDING' ?>
                            </span>
                        </td>
                        <td class="py-3 px-3 text-slate-300"><?= $ans ? "{$ans['score']}/{$ans['points_possible']}" : '—' ?></td>
                        <td class="py-3 px-3 text-right">
                            <a href="/ufc_v1/assessment/question.php?id=<?= $assessmentId ?>&q=<?= $q['question_number'] ?>" 
                               class="text-xs text-[#c9a84c] hover:underline font-semibold">
                                Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
