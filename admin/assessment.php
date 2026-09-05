<?php

/**
 * United Five Construction - Full Assessment Inspector & Audit Trail
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';
require_once __DIR__ . '/../components/report-body.php';

requireLogin();
$currentUser = getCurrentUser();

$assessmentId = (int)($_GET['id'] ?? 0);
$assessment = getAssessmentDetails($assessmentId);

if (!$assessment) {
    setFlashMessage('danger', 'Assessment not found.');
    header("Location: /ufc_v1/admin/assessments.php");
    exit;
}

$pdo = getDbConnection();
$phases = getAllPhases();

// Fetch all answers
$answersMap = getAssessmentAnswersMap($assessmentId);

// Fetch phase results
$stmtPR = $pdo->prepare("SELECT * FROM phase_results WHERE assessment_id = ?");
$stmtPR->execute([$assessmentId]);
$phaseResults = [];
while ($row = $stmtPR->fetch()) {
    $phaseResults[$row['phase_id']] = $row;
}

// Fetch explain blocks
$stmtEB = $pdo->prepare("SELECT eb.*, q.question_number, q.question_text FROM explain_blocks eb JOIN questions q ON eb.question_id = q.id WHERE eb.assessment_id = ?");
$stmtEB->execute([$assessmentId]);
$explainBlocks = $stmtEB->fetchAll();

// Fetch evidence files
$stmtFiles = $pdo->prepare("SELECT f.*, q.question_number FROM evidence_files f JOIN questions q ON f.question_id = q.id WHERE f.assessment_id = ? ORDER BY f.created_at DESC");
$stmtFiles->execute([$assessmentId]);
$evidenceFiles = $stmtFiles->fetchAll();
$evidenceFilesMap = [];
foreach ($evidenceFiles as $ef) {
    $evidenceFilesMap[$ef['question_id']] = $ef;
}

// Fetch CEO Overrides
$stmtOverrides = $pdo->prepare("SELECT o.*, u.name as ceo_name, p.phase_number FROM ceo_overrides o JOIN users u ON o.ceo_user_id = u.id JOIN phases p ON o.phase_id = p.id WHERE o.assessment_id = ?");
$stmtOverrides->execute([$assessmentId]);
$ceoOverrides = $stmtOverrides->fetchAll();

// Fetch Assessment History
$stmtHistory = $pdo->prepare("SELECT h.*, u.name as user_name FROM assessment_history h LEFT JOIN users u ON h.user_id = u.id WHERE h.assessment_id = ? ORDER BY h.created_at DESC");
$stmtHistory->execute([$assessmentId]);
$auditHistory = $stmtHistory->fetchAll();

$sla = getAssessmentSlaStatus($assessment);

$pageTitle = "Assessment #{$assessment['assessment_number']} — {$assessment['client_name']}";
require_once __DIR__ . '/../components/header.php';
$activePhaseNumber = (int)$assessment['current_phase'];
// require_once __DIR__ . '/../components/phase-nav.php';
?>

<?php
$status = $assessment['status'];
$badgeClass = 'bg-blue-950 text-blue-300 border-blue-600';
if ($status === 'PROCEED_TO_PROPOSAL') $badgeClass = 'bg-emerald-950 text-emerald-300 border-emerald-500';
if ($status === 'HOLD') $badgeClass = 'bg-amber-950 text-[#c9a84c] border-amber-500';
if ($status === 'NOT_A_FIT') $badgeClass = 'bg-red-950 text-red-300 border-red-600';
if ($status === 'ESCALATED') $badgeClass = 'bg-purple-950 text-purple-300 border-purple-500';
?>

<div class="space-y-6">

    <!-- ══ TOP ASSESSMENT DETAILS METADATA CARD ═════════════════════════════ -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <!-- Header Bar with Title, Status & Actions -->
        <div class="p-5 sm:p-6 bg-gradient-to-r from-[#0d1f3c] via-[#122849] to-[#0a172c] border-b border-[#1e3e68] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-[#1a3a5c] text-[#c9a84c] border border-[#234d7a]">
                        <?= htmlspecialchars($assessment['assessment_number']) ?>
                    </span>
                    <span class="text-xs text-slate-400">Created: <?= formatDate($assessment['created_at']) ?></span>
                </div>
                <h1 class="font-serif text-2xl sm:text-3xl font-bold text-white mt-1.5 flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-info text-[#c9a84c] text-xl"></i>
                    <span>Assessment Details</span>
                </h1>
            </div>

            <!-- Status & Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="px-3.5 py-1.5 rounded-full text-xs font-bold border tracking-wider uppercase <?= $badgeClass ?>">
                    <?= str_replace('_', ' ', $status) ?>
                </span>

                <a href="/ufc_v1/assessment/question.php?id=<?= $assessmentId ?>"
                    class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-all flex items-center gap-1.5">
                    <i class="fa-regular fa-pen-to-square text-[#c9a84c]"></i>
                    <span>Edit Assessment</span>
                </a>

                <a href="/ufc_v1/assessment/preview-pdf.php?id=<?= $assessmentId ?>"
                    target="_blank"
                    class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-all flex items-center gap-1.5">
                    <i class="fa-regular fa-eye text-blue-400"></i>
                    <span>View PDF Report</span>
                </a>

                <a href="/ufc_v1/api/export_pdf.php?id=<?= $assessmentId ?>"
                    class="px-4 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-xs font-bold rounded shadow transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf text-xs"></i>
                    <span>Download PDF</span>
                </a>
                <?php if ($status === 'HOLD'): ?>
                    <a href="/ufc_v1/assessment/requirements-letter.php?id=<?= $assessmentId ?>&phase=<?= $assessment['current_phase'] ?>"
                        target="_blank"
                        class="px-3.5 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-amber-300 border border-amber-600/50 text-xs font-semibold rounded">
                        Requirements Letter
                    </a>
                <?php elseif ($status === 'NOT_A_FIT'): ?>
                    <a href="/ufc_v1/assessment/decline-letter.php?id=<?= $assessmentId ?>"
                        target="_blank"
                        class="px-3.5 py-2 bg-red-950 hover:bg-red-900 text-red-300 border border-red-600/50 text-xs font-semibold rounded">
                        Decline Letter
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Metadata Grid -->
        <div class="p-5 sm:p-6 bg-[#081528]/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-5 text-xs">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Client Name</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-slate-200 border border-[#1e3e68] text-sm">
                        <?= htmlspecialchars($assessment['client_name'] ?: '—') ?>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Project Name</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-white border border-[#1e3e68] flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-building text-[#c9a84c]"></i>
                        <span><?= htmlspecialchars($assessment['project_name'] ?: '—') ?></span>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Contact</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-slate-200 border border-[#1e3e68]">
                        <?= htmlspecialchars($assessment['client_phone'] ?: '—') ?>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Email</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-slate-200 border border-[#1e3e68]">
                        <?= htmlspecialchars($assessment['client_email'] ?: '—') ?>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Created by</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-slate-200 border border-[#1e3e68]">
                        <?= htmlspecialchars($assessment['assessor_name'] ?: 'System') ?>
                        <span class="text-slate-400 text-[10px] block font-normal mt-0.5"><?= formatDate($assessment['created_at'], 'M j, Y H:i') ?></span>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Last Update</p>
                    <div class="bg-[#0d1f3c] rounded-lg p-3 font-semibold text-slate-200 border border-[#1e3e68]">
                        <?= htmlspecialchars($assessment['last_updated_by_name'] ?? $assessment['assessor_name'] ?? '—') ?>
                        <span class="text-slate-400 text-[10px] block font-normal mt-0.5"><?= formatDate($assessment['updated_at'], 'M j, Y H:i') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Switcher Tabs -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-1.5 flex flex-wrap items-center gap-2 shadow-md">
        <button type="button"
            id="tab-btn-check-report"
            onclick="switchViewTab('check-report')"
            class="view-tab-btn px-4 py-2 rounded-lg font-bold text-xs transition-all flex items-center gap-2 bg-[#c9a84c] text-[#060f1e] shadow">
            <i class="fa-solid fa-clipboard-check text-xs"></i>
            <span>Check Report &amp; Milestones</span>
            <?= $sla['dot_html'] ?>
        </button>

        <button type="button"
            id="tab-btn-phases"
            onclick="switchViewTab('phases')"
            class="view-tab-btn px-4 py-2 rounded-lg font-semibold text-xs transition-all flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#1a3a5c]">
            <i class="fa-solid fa-list-ol text-xs"></i>
            <span>Four-Phase Qualification Review</span>
        </button>

        <button type="button"
            id="tab-btn-report"
            onclick="switchViewTab('report')"
            class="view-tab-btn px-4 py-2 rounded-lg font-semibold text-xs transition-all flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#1a3a5c]">
            <i class="fa-regular fa-file-lines text-xs"></i>
            <span>Report Body</span>
        </button>

        <button type="button"
            id="tab-btn-audit"
            onclick="switchViewTab('audit')"
            class="view-tab-btn px-4 py-2 rounded-lg font-semibold text-xs transition-all flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#1a3a5c]">
            <i class="fa-solid fa-timeline text-xs"></i>
            <span>Audit History &amp; Overrides</span>
        </button>
    </div>

    <!-- ══ TAB CONTENT 1: CHECK REPORT & MILESTONES ═════════════════════════ -->
    <div id="tab-content-check-report" class="view-tab-pane space-y-6">
        <?php require __DIR__ . '/../components/check-report-card.php'; ?>
    </div>

    <!-- ══ TAB CONTENT 2: 4 PHASES QUALIFICATION REVIEW ═════════════════════ -->
    <div id="tab-content-phases" class="view-tab-pane space-y-4 hidden">
        <h2 class="font-serif text-xl font-bold text-slate-100">Four-Phase Qualification Review</h2>

        <?php foreach ($phases as $p):
            $pId = (int)$p['id'];
            $pNum = (int)$p['phase_number'];
            $pRes = $phaseResults[$pId] ?? null;
            $unlocked = isPhaseUnlocked($assessmentId, $pNum);
            $phaseQuestions = getApplicableQuestionsForPhase($assessmentId, $pNum);
        ?>
            <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl overflow-hidden shadow-md">
                <div class="p-4 bg-[#0a172c] flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#1e3e68]">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded bg-[#1a3a5c] text-[#c9a84c] font-bold text-xs flex items-center justify-center border border-[#234d7a]">
                            P<?= $pNum ?>
                        </span>
                        <div>
                            <h3 class="font-bold text-sm text-slate-100"><?= htmlspecialchars($p['title']) ?></h3>
                            <p class="text-[11px] text-slate-400 italic">"<?= htmlspecialchars($p['the_question']) ?>"</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php
                        $dispThresh = (!empty($p['threshold'])) ? (((float)$p['threshold'] <= 10.0 && (float)$p['threshold'] > 0) ? (float)$p['threshold'] * 10 : (float)$p['threshold']) : null;
                        ?>
                        <?php if ($pRes): ?>
                            <span class="text-xs font-semibold text-slate-300">Score: <?= $pRes['score_percent'] ?>%</span>
                            <?php if ($dispThresh !== null): ?>
                                <span class="text-[10px] font-mono text-slate-400 border border-slate-700 rounded px-1.5 py-0.5">
                                    Pass ≥ <?= number_format($dispThresh, 1) ?>% · Weight <?= number_format((float)$p['weight'] * 100, 1) ?>%
                                </span>
                            <?php endif; ?>
                            <span class="px-2.5 py-0.5 rounded text-[11px] font-bold border <?= ($pRes['status'] === 'PASS') ? 'bg-emerald-950 text-emerald-300 border-emerald-500' : 'bg-amber-950 text-[#c9a84c] border-amber-500' ?>">
                                <?= $pRes['status'] ?>
                            </span>
                        <?php else: ?>
                            <?php if ($dispThresh !== null): ?>
                                <span class="text-[10px] font-mono text-slate-500 border border-slate-700 rounded px-1.5 py-0.5">
                                    Pass ≥ <?= number_format($dispThresh, 1) ?>% · Weight <?= number_format((float)$p['weight'] * 100, 1) ?>%
                                </span>
                            <?php endif; ?>
                            <span class="px-2.5 py-0.5 rounded text-[11px] font-bold border bg-slate-800 text-slate-400 border-slate-700">
                                <?= $unlocked ? 'UNLOCKED' : 'LOCKED' ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($unlocked): ?>
                            <a href="/ufc_v1/assessment/question.php?id=<?= $assessmentId ?>&q=<?= $pNum . '.1' ?>"
                                class="px-3 py-1 bg-[#1a3a5c] hover:bg-[#234d7a] text-[#c9a84c] text-xs font-semibold rounded border border-[#1e3e68]">
                                Review Phase
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Questions Breakdown -->
                <?php if ($unlocked && !empty($phaseQuestions)): ?>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-[#1e3e68]">
                                    <th class="py-2 px-2">Q#</th>
                                    <th class="py-2 px-2">Question</th>
                                    <th class="py-2 px-2">Owner</th>
                                    <th class="py-2 px-2">Answer</th>
                                    <th class="py-2 px-2">Status</th>
                                    <th class="py-2 px-2">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#1e3e68]/50">
                                <?php foreach ($phaseQuestions as $q):
                                    $ans = $answersMap[$q['question_number']] ?? null;
                                ?>
                                    <tr class="hover:bg-[#1a3a5c]/20">
                                        <td class="py-2 px-2 font-mono font-bold text-[#c9a84c]"><?= $q['question_number'] ?></td>
                                        <td class="py-2 px-2 text-slate-200 max-w-sm"><?= htmlspecialchars($q['question_text']) ?></td>
                                        <td class="py-2 px-2 text-slate-400"><?= $q['owner'] ?></td>
                                        <td class="py-2 px-2 max-w-[220px]">
                                            <span class="text-xs font-normal text-slate-200 leading-snug break-words">
                                                <?= htmlspecialchars(formatAnswerValue($ans['answer_value'] ?? null, $q)) ?>
                                            </span>
                                            <?php if (isset($evidenceFilesMap[$q['id']])):
                                                $ef = $evidenceFilesMap[$q['id']];
                                            ?>
                                                <a href="/ufc_v1/uploads/<?= htmlspecialchars($ef['stored_filename']) ?>"
                                                    target="_blank"
                                                    title="View Evidence Document: <?= htmlspecialchars($ef['original_name']) ?>"
                                                    class="ml-2 text-[#c9a84c] hover:text-white inline-flex items-center gap-1 text-[11px] font-normal underline">
                                                    <i class="fa-solid fa-paperclip text-xs"></i>
                                                    <span>Document</span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 px-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= ($ans['status_light'] ?? '') === 'GREEN' ? 'badge-green' : (($ans['status_light'] ?? '') === 'AMBER' ? 'badge-amber' : (($ans['status_light'] ?? '') === 'RED' ? 'badge-red' : 'badge-neutral')) ?>">
                                                <?= $ans['status_light'] ?? 'PENDING' ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-2 text-slate-300"><?= $ans ? "{$ans['score']}/{$ans['points_possible']}" : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ══ TAB CONTENT 3: REPORT BODY ══════════════════════════════════════════ -->
    <div id="tab-content-report" class="view-tab-pane hidden">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-[#0d1f3c] border border-[#1e3e68] rounded-xl px-5 py-3 shadow-md">
            <div class="flex items-center gap-2">
                <i class="fa-regular fa-file-lines text-[#c9a84c]"></i>
                <span class="text-sm font-bold text-white">Report Body</span>
                <span class="text-xs text-slate-400 ml-1">&mdash; <?= htmlspecialchars($assessment['assessment_number']) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/ufc_v1/assessment/report.php?id=<?= $assessmentId ?>"
                   target="_blank"
                   class="px-3.5 py-1.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                    <span>Open in Full Page</span>
                </a>
                <a href="/ufc_v1/api/export_pdf.php?id=<?= $assessmentId ?>"
                   class="px-3.5 py-1.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-xs font-bold rounded shadow transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf text-xs"></i>
                    <span>Download PDF</span>
                </a>
            </div>
        </div>

        <!-- Report body render -->
        <div class="mt-4">
            <?= renderReportBody($assessmentId, false) ?>
        </div>
    </div>

    <!-- ══ TAB CONTENT 4: AUDIT HISTORY & OVERRIDES ══════════════════════════ -->
    <div id="tab-content-audit" class="view-tab-pane space-y-6 hidden">
        <!-- CEO Overrides & Review Panel (For CEO or Admin) -->
        <?php if (isCeo() || isAdmin()): ?>
            <div class="bg-[#0d1f3c] border-2 border-purple-600/60 rounded-xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#1e3e68]">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded bg-purple-900 text-purple-200 text-xs font-bold uppercase">Executive Panel</span>
                        <h3 class="font-serif font-bold text-sm text-slate-100">CEO / Legal Override Authority</h3>
                    </div>
                    <span class="text-xs text-slate-400">PDF Section 4 Specification</span>
                </div>

                <p class="text-xs text-slate-300">
                    The Chief Executive Officer may override a STOP trigger or clear an ESCALATION trigger. Written justification is recorded permanently in the audit log.
                </p>

                <form action="/ufc_v1/admin/ceo-override.php" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Target Phase</label>
                        <select name="phase_id" class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded text-xs text-slate-100 focus:border-[#c9a84c]">
                            <?php foreach ($phases as $p): ?>
                                <option value="<?= $p['id'] ?>">Phase <?= $p['phase_number'] ?> (<?= htmlspecialchars($p['title']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Trigger Type to Override</label>
                        <select name="trigger_type" class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded text-xs text-slate-100 focus:border-[#c9a84c]">
                            <option value="STOP">STOP Trigger (Override to Pass)</option>
                            <option value="ESCALATE">ESCALATE Trigger (Clear Flag)</option>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            Permanent Written Justification <span class="text-red-400">*</span>
                        </label>
                        <textarea name="justification" rows="2" required placeholder="State legal rationale, executive waiver, or mitigation terms..."
                            class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded text-xs text-slate-100 focus:border-[#c9a84c]"></textarea>
                    </div>

                    <div class="sm:col-span-3 text-right">
                        <button type="submit" class="px-5 py-2 bg-purple-700 hover:bg-purple-600 text-white font-bold text-xs rounded shadow transition-colors">
                            Record Executive Override
                        </button>
                    </div>
                </form>

                <?php if (!empty($ceoOverrides)): ?>
                    <div class="mt-4 pt-4 border-t border-[#1e3e68] space-y-2">
                        <h4 class="text-xs font-bold text-purple-300 uppercase">Recorded Executive Overrides:</h4>
                        <?php foreach ($ceoOverrides as $ov): ?>
                            <div class="p-3 rounded bg-[#060f1e] border border-purple-800/50 text-xs">
                                <div class="flex items-center justify-between text-slate-400 mb-1">
                                    <span class="font-bold text-purple-300">Phase <?= $ov['phase_number'] ?> &middot; <?= $ov['trigger_type'] ?> Override</span>
                                    <span><?= formatDate($ov['created_at'], 'M j, Y H:i') ?> by <?= htmlspecialchars($ov['ceo_name']) ?></span>
                                </div>
                                <p class="text-slate-200 italic">"<?= htmlspecialchars($ov['justification']) ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Audit Log History -->
        <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-6 shadow-xl">
            <h3 class="font-serif font-bold text-lg text-slate-100 mb-4">
                Assessment Change Audit History
            </h3>

            <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                <?php if (empty($auditHistory)): ?>
                    <div class="text-xs text-slate-400 italic">No history records yet.</div>
                <?php else: ?>
                    <?php foreach ($auditHistory as $hist): ?>
                        <div class="p-3 rounded-lg bg-[#060f1e] border border-[#1e3e68] text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="font-bold text-[#c9a84c]"><?= htmlspecialchars($hist['action']) ?></span>
                                <span class="text-slate-400 mx-1">&middot;</span>
                                <span class="text-slate-300"><?= htmlspecialchars($hist['details']) ?></span>
                            </div>
                            <div class="text-slate-500 font-mono shrink-0">
                                <?= formatDate($hist['created_at'], 'M j, H:i') ?> by <?= htmlspecialchars($hist['user_name'] ?? 'System') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function switchViewTab(tabKey) {
        const tabs = ['check-report', 'phases', 'report', 'audit'];
        tabs.forEach(t => {
            const pane = document.getElementById(`tab-content-${t}`);
            const btn  = document.getElementById(`tab-btn-${t}`);
            if (pane) {
                pane.classList.toggle('hidden', t !== tabKey);
            }
            if (btn) {
                if (t === tabKey) {
                    btn.className = 'view-tab-btn px-4 py-2 rounded-lg font-bold text-xs transition-all flex items-center gap-2 bg-[#c9a84c] text-[#060f1e] shadow';
                } else {
                    btn.className = 'view-tab-btn px-4 py-2 rounded-lg font-semibold text-xs transition-all flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#1a3a5c]';
                }
            }
        });

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabKey);
        window.history.replaceState({}, '', url.toString());
    }

    // Support auto-open from query param ?tab=...
    (function() {
        const params   = new URLSearchParams(window.location.search);
        const tabParam = params.get('tab');
        const valid    = ['check-report', 'phases', 'report', 'audit'];
        if (tabParam && valid.includes(tabParam)) {
            switchViewTab(tabParam);
        }
    })();
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>