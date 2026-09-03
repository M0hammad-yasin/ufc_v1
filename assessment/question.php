<?php

/**
 * United Five Construction - One-Question-At-A-Time Assessment Runner
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';
require_once __DIR__ . '/../includes/upload.php';

requireLogin();
$currentUser = getCurrentUser();

$assessmentId = (int)($_GET['id'] ?? 0);
$qNumber = trim($_GET['q'] ?? '');

$assessment = getAssessmentDetails($assessmentId);
if (!$assessment) {
    die("Assessment not found.");
}

// Check Question
if (empty($qNumber)) {
    // Default to first question of current phase
    $phaseNum = (int)$assessment['current_phase'];
    $applicable = getApplicableQuestionsForPhase($assessmentId, $phaseNum);
    $firstQ = $applicable[0] ?? null;
    $qNumber = $firstQ ? $firstQ['question_number'] : '1.1';
}

$question = getQuestionByNumber($qNumber);
if (!$question) {
    die("Question {$qNumber} not found.");
}

$activePhaseNumber = (int)$question['phase_number'];

// Security & Phase Locking Backend Verification
if (!isPhaseUnlocked($assessmentId, $activePhaseNumber)) {
    setFlashMessage('danger', "Phase {$activePhaseNumber} is locked. Previous phase must PASS first.");
    header("Location: /ufc_v1/admin/assessment.php?id={$assessmentId}");
    exit;
}

// Fetch existing answer if present
$pdo = getDbConnection();
$stmtAns = $pdo->prepare("SELECT * FROM assessment_answers WHERE assessment_id = ? AND question_id = ? LIMIT 1");
$stmtAns->execute([$assessmentId, $question['id']]);
$existingAnswer = $stmtAns->fetch();

// Fetch existing explain block
$existingExplain = getExplainBlock($assessmentId, (int)$question['id']);

// Fetch attached evidence files
$evidenceFiles = getEvidenceFilesForQuestion($assessmentId, (int)$question['id']);

// Progress
$progress = getPhaseQuestionsProgress($assessmentId, $activePhaseNumber, $qNumber);
$prevQuestion = getPreviousApplicableQuestion($assessmentId, $activePhaseNumber, $qNumber);
$nextQuestion = getNextApplicableQuestion($assessmentId, $activePhaseNumber, $qNumber);

// Question Options
$options = getQuestionOptions((int)$question['id']);

$pageTitle = "Question {$question['question_number']} — {$assessment['client_name']} — UFC";
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/phase-nav.php';
?>

<div class="max-w-4xl mx-auto mt-6">
    <!-- Progress Bar -->
    <div class="my-6">
        <div class="flex items-center justify-between text-xs font-semibold text-slate-400 mb-2">
            <span class="text-[#c9a84c] uppercase tracking-wider font-bold">
                Phase <?= $activePhaseNumber ?>: <?= htmlspecialchars($question['phase_title']) ?>
            </span>
            <span>
                Question <?= $progress['current_index'] ?> of <?= $progress['total_applicable'] ?> applicable
            </span>
        </div>
        <div class="w-full bg-[#1a3a5c] rounded-full h-2 overflow-hidden border border-[#1e3e68]">
            <div class="bg-gradient-to-r from-[#c9a84c] to-[#e4c468] h-2 rounded-full transition-all duration-300"
                style="width: <?= $progress['percent'] ?>%;"></div>
        </div>
    </div>

    <!-- Question Card -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-6 sm:p-10 shadow-2xl relative">
        <!-- Question Meta Badges -->
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="px-2.5 py-1 rounded text-xs font-mono font-bold bg-[#1a3a5c] text-white border border-[#234d7a]">
                Q <?= $question['question_number'] ?>
            </span>

            <span class="px-2.5 py-1 rounded text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                Owner: <?= $question['owner'] ?>
            </span>

            <?php if ($question['visibility'] === 'INTERNAL_ONLY'): ?>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-purple-950/80 text-purple-300 border border-purple-700">
                    🔒 Internal Only
                </span>
            <?php else: ?>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-blue-950/80 text-blue-300 border border-blue-700">
                    Client Facing
                </span>
            <?php endif; ?>

            <?php if ($question['trigger_type'] === 'HOLD'): ?>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-amber-950/80 text-[#c9a84c] border border-amber-700">
                    HOLD Trigger
                </span>
            <?php elseif ($question['trigger_type'] === 'STOP'): ?>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-red-950/80 text-red-300 border border-red-700">
                    STOP Trigger
                </span>
            <?php elseif ($question['trigger_type'] === 'ESCALATE'): ?>
                <span class="px-2.5 py-1 rounded text-xs font-semibold bg-purple-950/80 text-purple-300 border border-purple-700">
                    ESCALATE Trigger
                </span>
            <?php endif; ?>
        </div>

        <!-- Question Text -->
        <h2 class="font-serif text-xl sm:text-2xl font-semibold text-white leading-snug mb-6">
            <?= htmlspecialchars($question['question_text']) ?>
        </h2>

        <!-- Evidence Requirement Hint -->
        <?php if (!empty($question['evidence_required'])): ?>
            <div class="mb-8 p-3 rounded-lg bg-[#060f1e]/80 border border-[#1e3e68] text-xs text-slate-300 flex items-start gap-2">
                <svg class="w-4 h-4 text-[#c9a84c] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-bold text-slate-200">Evidence Required:</span>
                    <?= htmlspecialchars($question['evidence_required']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Question Form -->
        <form id="questionForm" action="/ufc_v1/assessment/save-answer.php" method="POST" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
            <input type="hidden" name="question_number" value="<?= $question['question_number'] ?>">
            <input type="hidden" name="phase_number" value="<?= $activePhaseNumber ?>">

            <!-- ANSWER WIDGETS -->
            <div class="answer-widget">
                <?php if ($question['response_type'] === 'YES_NO'): ?>
                    <?php
                    $val = $existingAnswer['answer_value'] ?? '';
                    ?>
                    <div class="grid grid-cols-2 gap-4 max-w-md">
                        <label class="flex items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($val === 'YES') ? 'border-emerald-500 bg-emerald-950/40 text-emerald-200' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?>" id="btn-yes">
                            <input type="radio" name="answer_value" value="YES" required <?= ($val === 'YES') ? 'checked' : '' ?> class="sr-only" onchange="handleAnswerChange()">
                            <span class="font-bold text-base tracking-wider">YES</span>
                        </label>
                        <label class="flex items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($val === 'NO') ? 'border-red-500 bg-red-950/40 text-red-200' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?>" id="btn-no">
                            <input type="radio" name="answer_value" value="NO" required <?= ($val === 'NO') ? 'checked' : '' ?> class="sr-only" onchange="handleAnswerChange()">
                            <span class="font-bold text-base tracking-wider">NO</span>
                        </label>
                    </div>

                <?php elseif ($question['response_type'] === 'YES_NO_NA'): ?>
                    <?php
                    $val = $existingAnswer['answer_value'] ?? '';
                    $naJust = $existingAnswer['na_justification'] ?? '';
                    ?>
                    <div class="space-y-4 max-w-lg">
                        <div class="grid grid-cols-3 gap-3">
                            <label class="flex items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition-all <?= ($val === 'YES') ? 'border-emerald-500 bg-emerald-950/40 text-emerald-200' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?>" id="btn-yes">
                                <input type="radio" name="answer_value" value="YES" required <?= ($val === 'YES') ? 'checked' : '' ?> class="sr-only" onchange="handleAnswerChange()">
                                <span class="font-bold text-sm tracking-wider">YES</span>
                            </label>
                            <label class="flex items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition-all <?= ($val === 'NO') ? 'border-red-500 bg-red-950/40 text-red-200' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?>" id="btn-no">
                                <input type="radio" name="answer_value" value="NO" required <?= ($val === 'NO') ? 'checked' : '' ?> class="sr-only" onchange="handleAnswerChange()">
                                <span class="font-bold text-sm tracking-wider">NO</span>
                            </label>
                            <label class="flex items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition-all <?= ($val === 'NOT_APPLICABLE') ? 'border-amber-500 bg-amber-950/40 text-amber-200' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?>" id="btn-na">
                                <input type="radio" name="answer_value" value="NOT_APPLICABLE" required <?= ($val === 'NOT_APPLICABLE') ? 'checked' : '' ?> class="sr-only" onchange="handleAnswerChange()">
                                <span class="font-bold text-xs tracking-wider text-center">NOT APPLICABLE</span>
                            </label>
                        </div>

                        <div id="na_justification_block" class="<?= ($val === 'NOT_APPLICABLE') ? '' : 'hidden' ?> p-4 rounded-lg bg-[#060f1e] border border-amber-500/50">
                            <label class="block text-xs font-semibold text-[#c9a84c] uppercase tracking-wider mb-1">
                                Required One-Line Justification for Not Applicable <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="na_justification" id="na_justification_input" value="<?= htmlspecialchars($naJust) ?>"
                                placeholder="e.g. New building construction with no prior work in the last 10 years."
                                class="w-full px-3 py-2 bg-[#0d1f3c] border border-[#1e3e68] rounded text-sm text-slate-100 placeholder-slate-500 focus:border-[#c9a84c]">
                        </div>
                    </div>

                <?php elseif ($question['response_type'] === 'SCALE_1_10'): ?>
                    <?php
                    $val = (int)($existingAnswer['answer_value'] ?? 8);
                    ?>
                    <div class="space-y-4">
                        <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
                            <?php for ($i = 1; $i <= 10; $i++):
                                $bandClass = ($i <= 4) ? 'hover:border-red-500 text-red-300' : (($i <= 7) ? 'hover:border-amber-500 text-[#c9a84c]' : 'hover:border-emerald-500 text-emerald-300');
                                $selectedClass = ($val === $i) ? (($i <= 4) ? 'bg-red-900/60 border-red-500 text-white font-bold ring-2 ring-red-400' : (($i <= 7) ? 'bg-amber-900/60 border-amber-500 text-white font-bold ring-2 ring-amber-400' : 'bg-emerald-900/60 border-emerald-500 text-white font-bold ring-2 ring-emerald-400')) : 'border-[#1e3e68] bg-[#060f1e]';
                            ?>
                                <label class="flex flex-col items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition-all <?= $selectedClass ?> <?= $bandClass ?> scale-btn" data-score="<?= $i ?>">
                                    <input type="radio" name="answer_value" value="<?= $i ?>" required <?= ($val === $i) ? 'checked' : '' ?> class="sr-only" onchange="handleScaleChange(<?= $i ?>)">
                                    <span class="text-lg font-bold"><?= $i ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>

                        <!-- Band legend -->
                        <div class="flex items-center justify-between text-xs px-1 text-slate-400">
                            <span class="text-red-400">1–4: RED (Deficient / Explain)</span>
                            <span class="text-[#c9a84c]">5–7: AMBER (Budget Range / Noted)</span>
                            <span class="text-emerald-400">8–10: GREEN (Ready / Optimal)</span>
                        </div>
                    </div>

                <?php elseif ($question['response_type'] === 'SINGLE_SELECT'): ?>
                    <?php $val = $existingAnswer['answer_value'] ?? ''; ?>
                    <div class="space-y-3">
                        <?php foreach ($options as $opt):
                            $isSelected = ($val === $opt['option_key']);
                        ?>
                            <label class="flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all <?= $isSelected ? 'border-[#c9a84c] bg-[#1a3a5c]/60' : 'border-[#1e3e68] bg-[#060f1e] hover:border-slate-500' ?> option-row">
                                <input type="radio" name="answer_value" value="<?= htmlspecialchars($opt['option_key']) ?>" required <?= $isSelected ? 'checked' : '' ?>
                                    class="mt-1 text-[#c9a84c] focus:ring-[#c9a84c] bg-[#060f1e] border-[#1e3e68]" onchange="handleAnswerChange()">
                                <div class="ml-3">
                                    <span class="font-medium text-sm text-slate-100 block"><?= htmlspecialchars($opt['option_label']) ?></span>
                                    <?php if ($opt['branch_action'] === 'HOLD'): ?>
                                        <span class="text-[11px] text-amber-400 mt-0.5 inline-block font-semibold">Hold item</span>
                                    <?php elseif ($opt['branch_action'] === 'SKIP'): ?>
                                        <span class="text-[11px] text-blue-400 mt-0.5 inline-block font-semibold">Branches ahead</span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($question['response_type'] === 'MULTI_SELECT'): ?>
                    <?php
                    $decodedAns = !empty($existingAnswer['answer_value']) ? json_decode($existingAnswer['answer_value'], true) : [];
                    $checkedKeys = $decodedAns['checked'] ?? [];
                    $notReqKeys = $decodedAns['not_required'] ?? [];
                    ?>
                    <div class="space-y-3">
                        <div class="text-xs text-slate-400 mb-2">Check all completed and issued disciplines. Toggle "Not required" for disciplines not in project scope.</div>
                        <?php foreach ($options as $opt):
                            $isChecked = in_array($opt['option_key'], $checkedKeys, true);
                            $isNotReq = in_array($opt['option_key'], $notReqKeys, true);
                        ?>
                            <div class="p-3.5 rounded-xl border border-[#1e3e68] bg-[#060f1e] flex flex-col sm:flex-row sm:items-center justify-between gap-3 checklist-item">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="checklist_checked[]" value="<?= htmlspecialchars($opt['option_key']) ?>" <?= $isChecked ? 'checked' : '' ?>
                                        class="w-4 h-4 text-[#c9a84c] rounded border-[#1e3e68] bg-[#0d1f3c] focus:ring-[#c9a84c]" onchange="handleChecklistChange()">
                                    <span class="ml-3 text-sm font-medium text-slate-200"><?= htmlspecialchars($opt['option_label']) ?></span>
                                </label>

                                <label class="flex items-center text-xs text-slate-400 cursor-pointer ml-7 sm:ml-0">
                                    <input type="checkbox" name="not_required[]" value="<?= htmlspecialchars($opt['option_key']) ?>" <?= $isNotReq ? 'checked' : '' ?>
                                        class="w-3.5 h-3.5 text-slate-500 rounded border-[#1e3e68] bg-[#0d1f3c]" onchange="handleChecklistChange()">
                                    <span class="ml-2">Not required for scope</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- EXPLAIN BLOCK COMPONENT -->
            <div id="explainBlockContainer" class="p-6 rounded-xl bg-[#081528] border-2 border-[#c9a84c]/60 shadow-xl space-y-4 <?= ($existingAnswer && $existingAnswer['status_light'] === 'RED') ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between pb-3 border-b border-[#1e3e68]">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#f87171] animate-pulse"></span>
                        <h3 class="font-serif font-bold text-sm text-[#c9a84c] tracking-wide">REQUIRED EXPLAIN BLOCK</h3>
                    </div>
                    <span class="text-[11px] text-slate-400">Deficiency noted · Mandatory before advance</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        1. Reason / Deficiency Explanation (Min 20 characters) <span class="text-red-400">*</span>
                    </label>
                    <textarea name="explain_reason" id="explain_reason" rows="3" minlength="5"
                        placeholder="Provide detailed explanation of the deficiency, current status, and resolution path..."
                        class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:border-[#c9a84c]"><?= htmlspecialchars($existingExplain['reason'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            2. Responsible Party <span class="text-red-400">*</span>
                        </label>
                        <select name="explain_responsible_party" id="explain_responsible_party"
                            class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 focus:border-[#c9a84c]">
                            <?php
                            $parties = ['CLIENT', 'LENDER', 'ARCHITECT', 'ENGINEER', 'EXPEDITOR', 'UFC', 'OTHER'];
                            $currParty = $existingExplain['responsible_party'] ?? 'CLIENT';
                            foreach ($parties as $p):
                            ?>
                                <option value="<?= $p ?>" <?= ($currParty === $p) ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            3. Target Cure Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" name="explain_target_cure_date" id="explain_target_cure_date"
                            value="<?= htmlspecialchars($existingExplain['target_cure_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>"
                            class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 focus:border-[#c9a84c]">
                    </div>
                </div>

                <!-- Evidence Document Upload -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        4. Supporting Evidence / Document (PDF, DWG, Image, Word)
                    </label>
                    <input type="file" name="evidence_file"
                        class="block w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#1a3a5c] file:text-slate-200 hover:file:bg-[#234d7a]">

                    <?php if (!empty($evidenceFiles)): ?>
                        <div class="mt-2 text-xs text-emerald-400 flex items-center gap-2">
                            <i class="fa-solid fa-paperclip"></i>
                            <span>Attached: <strong><?= htmlspecialchars($evidenceFiles[0]['original_name']) ?></strong> (<?= round($evidenceFiles[0]['file_size'] / 1024) ?> KB)</span>
                            <a href="/ufc_v1/uploads/<?= htmlspecialchars($evidenceFiles[0]['stored_filename']) ?>" target="_blank" class="underline text-[#c9a84c] hover:text-white ml-2 inline-flex items-center gap-1 font-semibold">
                                <span>View Document</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Client Message Box (if applicable) -->
            <?php if (!empty($question['client_message'])): ?>
                <div class="p-4 rounded-lg bg-[#060f1e]/60 border border-[#1e3e68] text-xs text-slate-400">
                    <span class="font-bold text-[#c9a84c] block mb-1">Client Letter Notification Preview:</span>
                    "<?= htmlspecialchars($question['client_message']) ?>"
                </div>
            <?php endif; ?>

            <!-- Navigation Buttons -->
            <div class="pt-6 border-t border-[#1e3e68] flex items-center justify-between">
                <?php if ($prevQuestion): ?>
                    <a href="/ufc_v1/assessment/question.php?id=<?= $assessmentId ?>&q=<?= $prevQuestion['question_number'] ?>"
                        class="px-5 py-2.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-semibold rounded-md border border-[#1e3e68] transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Previous</span>
                    </a>
                <?php else: ?>
                    <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>"
                        class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">
                        Exit Assessment
                    </a>
                <?php endif; ?>

                <button type="submit" id="nextButton"
                    class="px-8 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-sm font-bold rounded-md shadow-lg transition-all flex items-center gap-2">
                    <span><?= $nextQuestion ? 'Save & Next Question' : 'Complete Phase Gate' ?></span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function handleAnswerChange() {
        const selected = document.querySelector('input[name="answer_value"]:checked');
        const explainBox = document.getElementById('explainBlockContainer');
        const naBox = document.getElementById('na_justification_block');
        const qNum = "<?= $question['question_number'] ?>";
        const triggerType = "<?= $question['trigger_type'] ?>";

        if (!selected) return;
        const val = selected.value;

        // Reset button styles
        document.querySelectorAll('.answer-widget label').forEach(el => {
            el.classList.remove('border-emerald-500', 'bg-emerald-950/40', 'border-red-500', 'bg-red-950/40', 'border-amber-500', 'bg-amber-950/40', 'border-[#c9a84c]', 'bg-[#1a3a5c]/60');
        });

        if (val === 'YES') {
            const btn = document.getElementById('btn-yes');
            if (btn) btn.classList.add('border-emerald-500', 'bg-emerald-950/40');
            if (explainBox) explainBox.classList.add('hidden');
            if (naBox) naBox.classList.add('hidden');
        } else if (val === 'NO') {
            const btn = document.getElementById('btn-no');
            if (btn) btn.classList.add('border-red-500', 'bg-red-950/40');
            if (explainBox) explainBox.classList.remove('hidden');
            if (naBox) naBox.classList.add('hidden');
        } else if (val === 'NOT_APPLICABLE') {
            const btn = document.getElementById('btn-na');
            if (btn) btn.classList.add('border-amber-500', 'bg-amber-950/40');
            if (explainBox) explainBox.classList.add('hidden');
            if (naBox) naBox.classList.remove('hidden');
        } else {
            // Single select
            const parent = selected.closest('label');
            if (parent) parent.classList.add('border-[#c9a84c]', 'bg-[#1a3a5c]/60');

            if (val === 'NOT_STARTED' || val === 'NOT_DETERMINED' || (qNum === '1.1' && !['APPROVED_NO_PERMIT', 'APPROVED_PERMIT_ISSUED'].includes(val))) {
                if (explainBox) explainBox.classList.remove('hidden');
            } else {
                if (explainBox) explainBox.classList.add('hidden');
            }
        }
    }

    function handleScaleChange(score) {
        const explainBox = document.getElementById('explainBlockContainer');
        document.querySelectorAll('.scale-btn').forEach(btn => {
            btn.classList.remove('ring-2', 'ring-red-400', 'ring-amber-400', 'ring-emerald-400', 'bg-red-900/60', 'bg-amber-900/60', 'bg-emerald-900/60');
        });

        const activeBtn = document.querySelector(`.scale-btn[data-score="${score}"]`);
        if (activeBtn) {
            if (score <= 4) {
                activeBtn.classList.add('ring-2', 'ring-red-400', 'bg-red-900/60');
                if (explainBox) explainBox.classList.remove('hidden');
            } else if (score <= 7) {
                activeBtn.classList.add('ring-2', 'ring-amber-400', 'bg-amber-900/60');
                if (explainBox) explainBox.classList.add('hidden');
            } else {
                activeBtn.classList.add('ring-2', 'ring-emerald-400', 'bg-emerald-900/60');
                if (explainBox) explainBox.classList.add('hidden');
            }
        }
    }

    function handleChecklistChange() {
        const checked = document.querySelectorAll('input[name="checklist_checked[]"]:checked').length;
        const total = document.querySelectorAll('input[name="checklist_checked[]"]').length;
        const explainBox = document.getElementById('explainBlockContainer');

        if (checked < total) {
            if (explainBox) explainBox.classList.remove('hidden');
        } else {
            if (explainBox) explainBox.classList.add('hidden');
        }
    }

    // Client-side Validation on Submit
    document.getElementById('questionForm').addEventListener('submit', function(e) {
        const explainBox = document.getElementById('explainBlockContainer');
        if (explainBox && !explainBox.classList.contains('hidden')) {
            const reasonInput = document.getElementById('explain_reason');
            if (reasonInput && reasonInput.value.trim().length < 20) {
                e.preventDefault();
                alert("The Explain Block requires a minimum 20-character explanation for this item.");
                reasonInput.focus();
                return false;
            }
        }
        const naBox = document.getElementById('na_justification_block');
        if (naBox && !naBox.classList.contains('hidden')) {
            const naInput = document.getElementById('na_justification_input');
            if (naInput && naInput.value.trim().length < 5) {
                e.preventDefault();
                alert("Please provide the required one-line justification for marking Not Applicable.");
                naInput.focus();
                return false;
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>