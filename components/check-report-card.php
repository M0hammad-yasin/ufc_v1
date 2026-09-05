<?php

/**
 * United Five Construction - Reusable "Check Report" Milestone & Lifecycle Tracker Component
 *
 * Tracker: 14-day lifecycle timer per assessment (independent of assessment status).
 * - Start Tracker   → creates tracker row
 * - Action dropdown → ASSESSMENT_COMPLETED, DISCARDED, REJECTED + email shortcuts
 * - Resume          → restarts 14-day timer when tracker is stopped
 */

if (!isset($assessment) || empty($assessment['id'])) {
    return;
}

$pdo = getDbConnection();
$assessmentId = (int)$assessment['id'];

// ── SLA & Status ──────────────────────────────────────────────────────────
$sla         = getAssessmentSlaStatus($assessment);
$status      = $assessment['status'] ?? 'IN_PROGRESS';
$creatorName = !empty($assessment['assessor_name']) ? $assessment['assessor_name'] : 'System';
$updaterName = !empty($assessment['last_updated_by_name']) ? $assessment['last_updated_by_name'] : $creatorName;

// ── Tracker state ─────────────────────────────────────────────────────────
$tracker        = getAssessmentTracker($assessmentId, $pdo);
$trackerExists  = ($tracker !== null);
$trackerActive  = $trackerExists && $tracker['is_active'];
$trackerStopped = $trackerExists && !$tracker['is_active'];
$trackerExpired = $trackerExists && $tracker['is_expired'];
$trackerDays    = $tracker['days_elapsed']   ?? 0;
$trackerLeft    = $tracker['days_remaining'] ?? 14;

// Track if new timer was started & days since initial start
$timerCycles    = 1;
$daysSinceFirst = $trackerDays;
if ($trackerExists) {
    $timerCycles = (int)($tracker['timer_cycles'] ?? 1);
    try {
        $firstDt = new DateTime($tracker['first_started_at'] ?? $tracker['timer_started_at']);
        $nowDt   = new DateTime();
        $daysSinceFirst = (int)$firstDt->diff($nowDt)->days;
    } catch (Exception $e) {
        $daysSinceFirst = $trackerDays;
    }
}

// ── Phase utility flags ───────────────────────────────────────────────────
$allPhasesAssessed  = allPhasesAssessed($assessmentId, $pdo);
$allPhasesPassed    = allPhasesPassed($assessmentId, $pdo);
$hasPhaseOnHold     = hasPhaseOnHold($assessmentId, $pdo);
$milestonesUnlocked = $allPhasesPassed;

// ── Checkboxes ────────────────────────────────────────────────────────────
// Checkbox 1 is AUTO (read-only): checked when all 4 phases have been assessed
$chkAssessment        = $allPhasesAssessed;
// Checkboxes 2-4 are user-controlled but LOCKED if not all phases passed
$chkWalkThrough       = (bool)($assessment['checkpoint_client_meetup']   ?? 0);
$chkProposalSubmission = (bool)($assessment['checkpoint_build_proposal']  ?? 0);
$chkFinalBid          = (bool)($assessment['checkpoint_final_bid']        ?? 0);

// ── Assessment status badge ───────────────────────────────────────────────
$statusBadgeClass = 'bg-blue-950/80 text-blue-300 border-blue-600';
$statusLabel = 'In Progress';

if ($status === 'PROCEED_TO_PROPOSAL') {
    $statusBadgeClass = 'bg-emerald-950/80 text-emerald-300 border-emerald-500';
    $statusLabel = 'Passed · Proceed to Proposal';
} elseif ($status === 'HOLD') {
    $statusBadgeClass = 'bg-amber-950/80 text-[#c9a84c] border-amber-500';
    $statusLabel = 'HOLD — Requirements Pending';
} elseif ($status === 'NOT_A_FIT') {
    $statusBadgeClass = 'bg-red-950/80 text-red-300 border-red-500';
    $statusLabel = 'Not A Fit';
} elseif ($status === 'ESCALATED') {
    $statusBadgeClass = 'bg-purple-950/80 text-purple-300 border-purple-500';
    $statusLabel = 'Escalated · CEO Review';
}
?>


<div id="check-report-card" class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden mb-8 transition-all">
    <!-- Header Banner -->
    <div class="bg-[#0a172c] border-b border-[#1e3e68] p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Left: Title & Meta Info -->
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span class="px-2.5 py-0.5 rounded bg-[#1a3a5c] text-[#c9a84c] font-mono font-bold text-xs border border-[#234d7a]">
                        <?= htmlspecialchars($assessment['assessment_number']) ?>
                    </span>
                    <span id="header-status-badge" class="px-2.5 py-0.5 rounded text-xs font-bold border <?= $statusBadgeClass ?>">
                        <?= $statusLabel ?>
                    </span>
                    <span class="px-2 py-0.5 rounded bg-[#1a3a5c] text-slate-300 font-semibold text-[11px] border border-[#234d7a]">
                        Phase <?= (int)$assessment['current_phase'] ?> of 4
                    </span>
                </div>
                <h2 class="font-serif text-xl sm:text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                    <span>Check Report &amp; Milestone Tracker</span>
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Client Lead: <strong class="text-slate-200"><?= htmlspecialchars($assessment['client_name']) ?></strong>
                    <?php if (!empty($assessment['project_address'])): ?>
                        &middot; <span class="text-slate-400"><?= htmlspecialchars($assessment['project_address']) ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Right: Action & SLA Notification -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <!-- Primary Tracker Action Area (Only CEO & PM) -->
                <?php if (hasRole(['ceo', 'pm'])): ?>
                    <div id="sla-badge-container">
                        <?= $sla['badge_html'] ?>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <!-- ── Tracker Active: Show Action Status Dropdown ─────────── -->
                        <div class="relative inline-block text-left" id="action-status-container">
                            <button type="button"
                                id="btn-action-status"
                                onclick="toggleStatusDropdown(event)"
                                class="px-4 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-xs font-bold rounded shadow transition-all flex items-center gap-2 cursor-pointer">
                                <i class="fa-solid fa-bolt text-xs"></i>
                                <span>Action Status</span>
                                <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="status-dropdown-menu" class="hidden absolute right-0 mt-2 w-64 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-2xl z-50 overflow-hidden py-1">
                                <div class="px-3.5 py-2 border-b border-[#1e3e68] text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    Tracker Actions
                                </div>

                                <?php if ($trackerStopped): ?>
                                    <button type="button"
                                        id="btn-resume-tracker"
                                        onclick="resumeTracker(<?= $assessmentId ?>)"
                                        class="px-3.5 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-bold rounded shadow border border-[#1e3e68] transition-all flex items-center gap-2 cursor-pointer">
                                        <i class="fa-solid fa-rotate-right text-xs text-[#c9a84c]"></i>
                                        <span>Resume (New 14 Days)</span>
                                    </button>
                                <?php endif; ?>
                                <!-- 1. Assessment Completed — dynamic render ONLY if all 4 phases passed -->
                                <?php if ($allPhasesPassed && $trackerActive): ?>
                                    <button type="button"
                                        onclick="setTrackerStatus(<?= $assessmentId ?>, 'ASSESSMENT_COMPLETED')"
                                        class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-emerald-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                                        <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                                        <div>
                                            <div>Assessment Completed</div>
                                            <div class="text-[10px] text-slate-400 font-normal">All 4 phases passed · stop timer</div>
                                        </div>
                                    </button>
                                <?php endif; ?>

                                <!-- 2. Discard (14 days passed) — show but lock if < 14 days with (i) tooltip -->
                                <?php if ($trackerExpired): ?>
                                    <button type="button"
                                        onclick="setTrackerStatus(<?= $assessmentId ?>, 'DISCARDED')"
                                        class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-amber-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                                        <i class="fa-solid fa-ban text-amber-400 text-sm"></i>
                                        <div>
                                            <div>Discard</div>
                                            <div class="text-[10px] text-slate-400 font-normal">14 days elapsed since timer started</div>
                                        </div>
                                    </button>
                                <?php else: ?>
                                    <div class="w-full px-3.5 py-2.5 text-xs font-semibold text-slate-500 flex items-center gap-2.5 cursor-not-allowed select-none bg-slate-900/40"
                                        title="14 days have not elapsed or passed yet. <?= $trackerLeft ?> day(s) remaining.">
                                        <i class="fa-solid fa-ban text-slate-600 text-sm"></i>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-500">Discard</span>
                                                <span class="px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-400 text-[10px] border border-slate-700 flex items-center gap-1 cursor-help"
                                                    title="14 days have not elapsed or passed yet. <?= $trackerLeft ?> day(s) remaining.">
                                                    <i class="fa-solid fa-info-circle text-[9px] text-amber-400"></i>
                                                    <span><?= $trackerLeft ?>d left</span>
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-slate-600 font-normal">Locked (14 days not elapsed)</div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- 3. Rejected / Not Fit (client ran away or client requirement insufficient) -->
                                <?php if ($trackerActive): ?>
                                    <button type="button"
                                        onclick="setTrackerStatus(<?= $assessmentId ?>, 'REJECTED')"
                                        class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-red-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                                        <i class="fa-solid fa-circle-xmark text-red-400 text-sm"></i>
                                        <div>
                                            <div>Rejected / Not Fit</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Client ran away or insufficient reqs</div>
                                        </div>
                                    </button>
                                <?php else: ?>
                                    <div class="w-full px-3.5 py-2.5 text-xs font-semibold text-slate-500 flex items-center gap-2.5 cursor-not-allowed select-none bg-slate-900/40"
                                        title="14 days have not elapsed or passed yet. <?= $trackerLeft ?> day(s) remaining.">
                                        <i class="fa-solid fa-ban text-slate-600 text-sm"></i>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-500">Rejected / Not Fit</span>
                                            </div>
                                            <div class="text-[10px] text-slate-600 font-normal">Locked (tracker is not started)</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="border-t border-[#1e3e68] my-1"></div>

                                <!-- 4. Send Requirements Letter — ONLY when a phase has status HOLD due to client insufficient data -->
                                <?php if ($hasPhaseOnHold): ?>
                                    <button type="button"
                                        onclick="openEmailModal('send_letter')"
                                        class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-blue-300 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                                        <i class="fa-solid fa-envelope-open-text text-blue-400 text-sm"></i>
                                        <div>
                                            <div>Send Requirements Letter</div>
                                            <div class="text-[10px] text-slate-400 font-normal">Phase on HOLD · Request info</div>
                                        </div>
                                    </button>
                                <?php endif; ?>

                                <!-- 5. Send Lead Summary -->
                                <button type="button"
                                    onclick="openEmailModal('send_lead_summary')"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-sky-300 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <i class="fa-solid fa-address-card text-sky-400 text-sm"></i>
                                    <div>
                                        <div>Send Lead &amp; Contact Data</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Email lead summary sheet</div>
                                    </div>
                                </button>

                                <div class="border-t border-[#1e3e68] my-1"></div>

                                <!-- 6. Edit Assessment -->
                                <a href="<?= BASE_URL ?>/assessment/question.php?id=<?= $assessmentId ?>"
                                    class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-slate-300 hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                                    <i class="fa-solid fa-play text-[#c9a84c] text-xs"></i>
                                    <span>Edit assessment</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Four-Phase Navigation / Gate Progress Grid -->
    <div class="p-4 sm:p-5 bg-[#0a172c]/70 border-b border-[#1e3e68]">
        <?php
        $activePhaseNumber = (int)($assessment['current_phase'] ?? 1);
        require __DIR__ . '/phase-nav.php';
        ?>
    </div>

    <!-- 4 Milestone Checkboxes Section (Only CEO & PM) -->
    <?php if (hasRole(['ceo', 'pm'])): ?>
        <div class="p-6 bg-[#081528]/50">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-serif text-sm font-bold text-slate-200 uppercase tracking-wider">
                            Lead Qualification &amp; Conversion Milestones
                        </h3>
                        <?php if (!$milestonesUnlocked): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-950/80 text-amber-300 border border-amber-600/50 flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[9px]"></i>
                                <span>Milestones 2-4 Locked (Requires All 4 Phases Passed)</span>
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-600/50 flex items-center gap-1">
                                <i class="fa-solid fa-lock-open text-[9px]"></i>
                                <span>Milestones Unlocked</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Track key lifecycle checkpoints for this lead below. Changes save automatically.</p>
                </div>
                <span id="milestone-save-status" class="hidden text-xs font-semibold text-emerald-400 flex items-center gap-1">
                    <i class="fa-solid fa-check"></i> Saved
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Assessment Checkbox (ALWAYS LOCKED, AUTO-CHECKED IF ALL 4 PHASES ASSESSED) -->
                <div class="group relative flex items-start gap-3.5 p-4 rounded-xl border select-none transition-all <?= $chkAssessment ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68]' ?>"
                    title="Auto-verified: Checked automatically when all 4 phases are assessed. Always locked for manual input.">
                    <div class="flex items-center h-5 mt-0.5">
                        <input type="checkbox"
                            id="chk-assessment"
                            <?= $chkAssessment ? 'checked' : '' ?>
                            disabled
                            class="w-4 h-4 rounded text-emerald-500 bg-[#060f1e] border-[#1e3e68] cursor-not-allowed opacity-90 pointer-events-none">
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-100">1. Assessment</span>
                                <i class="fa-solid fa-lock text-[10px] text-slate-500" title="Locked (automatic gate)"></i>
                            </div>
                            <span id="badge-assessment" class="text-[10px] font-bold px-2 py-0.5 rounded <?= $chkAssessment ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/60' : 'bg-slate-800 text-slate-400' ?>">
                                <?= $chkAssessment ? 'Completed' : 'Pending' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 leading-snug">
                            Auto-verified once all 4 phases are run &amp; questions completed.
                        </p>
                        <div id="time-assessment" class="text-[10px] text-slate-500 mt-2 font-mono">
                            <?= $chkAssessment ? 'All 4 phases assessed' : 'Pending completion of all 4 phases' ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Walk Through Checkbox (LOCKED IF NOT ALL PHASES PASSED) -->
                <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all <?= $milestonesUnlocked ? 'cursor-pointer hover:border-[#2a558c]' : 'cursor-not-allowed opacity-60' ?> <?= $chkWalkThrough ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68]' ?>"
                    title="<?= $milestonesUnlocked ? 'Toggle Walk Through milestone' : 'Locked: All 4 phases must have PASS status to unlock this milestone.' ?>">
                    <div class="flex items-center h-5 mt-0.5">
                        <input type="checkbox"
                            id="chk-walk-through"
                            data-checkpoint="walk_through"
                            data-assessment-id="<?= (int)$assessment['id'] ?>"
                            <?= $chkWalkThrough ? 'checked' : '' ?>
                            <?= !$milestonesUnlocked ? 'disabled' : '' ?>
                            class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 <?= $milestonesUnlocked ? 'cursor-pointer' : 'cursor-not-allowed' ?> transition-colors">
                    </div>
                    <div class="flex-1 select-none">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">2. Walk Through</span>
                                <?php if (!$milestonesUnlocked): ?>
                                    <i class="fa-solid fa-lock text-[10px] text-amber-500/70" title="Locked (requires all phases PASS)"></i>
                                <?php endif; ?>
                            </div>
                            <span id="badge-walk-through" class="text-[10px] font-bold px-2 py-0.5 rounded <?= $chkWalkThrough ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/60' : 'bg-slate-800 text-slate-400' ?>">
                                <?= $chkWalkThrough ? 'Completed' : 'Pending' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 leading-snug">
                            On-site / virtual project walkthrough &amp; discovery held.
                        </p>
                        <div id="time-walk-through" class="text-[10px] text-slate-500 mt-2 font-mono">
                            <?= $assessment['checkpoint_client_meetup_at'] ? 'Marked: ' . formatDate($assessment['checkpoint_client_meetup_at'], 'M j, Y H:i') : 'Pending walkthrough' ?>
                        </div>
                    </div>
                </label>

                <!-- 3. Proposal Submission Checkbox (LOCKED IF NOT ALL PHASES PASSED) -->
                <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all <?= $milestonesUnlocked ? 'cursor-pointer hover:border-[#2a558c]' : 'cursor-not-allowed opacity-60' ?> <?= $chkProposalSubmission ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68]' ?>"
                    title="<?= $milestonesUnlocked ? 'Toggle Proposal Submission milestone' : 'Locked: All 4 phases must have PASS status to unlock this milestone.' ?>">
                    <div class="flex items-center h-5 mt-0.5">
                        <input type="checkbox"
                            id="chk-proposal-submission"
                            data-checkpoint="proposal_submission"
                            data-assessment-id="<?= (int)$assessment['id'] ?>"
                            <?= $chkProposalSubmission ? 'checked' : '' ?>
                            <?= !$milestonesUnlocked ? 'disabled' : '' ?>
                            class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 <?= $milestonesUnlocked ? 'cursor-pointer' : 'cursor-not-allowed' ?> transition-colors">
                    </div>
                    <div class="flex-1 select-none">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">3. Proposal Submission</span>
                                <?php if (!$milestonesUnlocked): ?>
                                    <i class="fa-solid fa-lock text-[10px] text-amber-500/70" title="Locked (requires all phases PASS)"></i>
                                <?php endif; ?>
                            </div>
                            <span id="badge-proposal-submission" class="text-[10px] font-bold px-2 py-0.5 rounded <?= $chkProposalSubmission ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/60' : 'bg-slate-800 text-slate-400' ?>">
                                <?= $chkProposalSubmission ? 'Completed' : 'Pending' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 leading-snug">
                            Full scope, line items &amp; initial pricing delivered.
                        </p>
                        <div id="time-proposal-submission" class="text-[10px] text-slate-500 mt-2 font-mono">
                            <?= $assessment['checkpoint_build_proposal_at'] ? 'Marked: ' . formatDate($assessment['checkpoint_build_proposal_at'], 'M j, Y H:i') : 'Pending proposal' ?>
                        </div>
                    </div>
                </label>

                <!-- 4. Final Bid Checkbox (LOCKED IF NOT ALL PHASES PASSED) -->
                <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all <?= $milestonesUnlocked ? 'cursor-pointer hover:border-[#2a558c]' : 'cursor-not-allowed opacity-60' ?> <?= $chkFinalBid ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68]' ?>"
                    title="<?= $milestonesUnlocked ? 'Toggle Final Bid milestone' : 'Locked: All 4 phases must have PASS status to unlock this milestone.' ?>">
                    <div class="flex items-center h-5 mt-0.5">
                        <input type="checkbox"
                            id="chk-final-bid"
                            data-checkpoint="final_bid"
                            data-assessment-id="<?= (int)$assessment['id'] ?>"
                            <?= $chkFinalBid ? 'checked' : '' ?>
                            <?= !$milestonesUnlocked ? 'disabled' : '' ?>
                            class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 <?= $milestonesUnlocked ? 'cursor-pointer' : 'cursor-not-allowed' ?> transition-colors">
                    </div>
                    <div class="flex-1 select-none">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">4. Final Bid</span>
                                <?php if (!$milestonesUnlocked): ?>
                                    <i class="fa-solid fa-lock text-[10px] text-amber-500/70" title="Locked (requires all phases PASS)"></i>
                                <?php endif; ?>
                            </div>
                            <span id="badge-final-bid" class="text-[10px] font-bold px-2 py-0.5 rounded <?= $chkFinalBid ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/60' : 'bg-slate-800 text-slate-400' ?>">
                                <?= $chkFinalBid ? 'Completed' : 'Pending' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 leading-snug">
                            Final revised bid &amp; contract execution confirmed.
                        </p>
                        <div id="time-final-bid" class="text-[10px] text-slate-500 mt-2 font-mono">
                            <?= (!empty($assessment['checkpoint_final_bid_at'])) ? 'Marked: ' . formatDate($assessment['checkpoint_final_bid_at'], 'M j, Y H:i') : 'Pending final bid' ?>
                        </div>
                    </div>
                </label>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Check Report Interactive Milestones Script -->
<script>
    (function() {
        const checkboxes = document.querySelectorAll('#check-report-card input[type="checkbox"][data-checkpoint]');
        const saveStatus = document.getElementById('milestone-save-status');
        const slaContainer = document.getElementById('sla-badge-container');
        const slaLabel = document.getElementById('sla-label');
        const updaterNameEl = document.getElementById('meta-last-updater-name');
        const updatedAtEl = document.getElementById('meta-last-updated-at');

        checkboxes.forEach(chk => {
            chk.addEventListener('change', async function() {
                if (this.disabled) return;
                const checkpoint = this.dataset.checkpoint;
                const assessmentId = this.dataset.assessmentId;
                const isChecked = this.checked;
                const parentLabel = this.closest('label');
                const badge = document.getElementById(`badge-${checkpoint.replace(/_/g, '-')}`);
                const timeEl = document.getElementById(`time-${checkpoint.replace(/_/g, '-')}`);

                // Visual toggle feedback
                if (parentLabel) {
                    parentLabel.classList.toggle('bg-[#122849]/90', isChecked);
                    parentLabel.classList.toggle('border-emerald-500/50', isChecked);
                    parentLabel.classList.toggle('shadow-md', isChecked);
                    parentLabel.classList.toggle('bg-[#0a172c]/70', !isChecked);
                    parentLabel.classList.toggle('border-[#1e3e68]', !isChecked);
                }

                if (badge) {
                    badge.textContent = isChecked ? 'Completed' : 'Pending';
                    badge.className = isChecked ?
                        'text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-600/60' :
                        'text-[10px] font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-400';
                }

                try {
                    const response = await fetch('<?= BASE_URL ?>/api/update_milestones.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            assessment_id: assessmentId,
                            checkpoint: checkpoint,
                            value: isChecked
                        })
                    });

                    if (!response.ok) throw new Error('Update failed');
                    const data = await response.json();

                    if (data.success) {
                        if (timeEl) {
                            timeEl.textContent = isChecked && data.checkpoint_at ? `Marked: ${data.checkpoint_at}` : 'Pending update';
                        }
                        if (updaterNameEl && data.last_updated_by) {
                            updaterNameEl.textContent = data.last_updated_by;
                        }
                        if (updatedAtEl && data.last_updated_at) {
                            updatedAtEl.textContent = data.last_updated_at;
                        }
                        if (slaContainer && data.sla && data.sla.badge_html) {
                            slaContainer.innerHTML = data.sla.badge_html;
                        }
                        if (slaLabel && data.sla && data.sla.label) {
                            slaLabel.textContent = data.sla.label;
                        }

                        // Show fleeting save confirmation
                        if (saveStatus) {
                            saveStatus.classList.remove('hidden');
                            setTimeout(() => saveStatus.classList.add('hidden'), 2500);
                        }
                    }
                } catch (err) {
                    console.error('Milestone save error:', err);
                    alert('Could not update milestone. Please refresh and try again.');
                    this.checked = !isChecked; // Revert on failure
                }
            });
        });
    })();

    window.toggleStatusDropdown = function(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('status-dropdown-menu');
        if (menu) menu.classList.toggle('hidden');
    };

    document.addEventListener('click', function(e) {
        const container = document.getElementById('action-status-container');
        const menu = document.getElementById('status-dropdown-menu');
        if (menu && container && !container.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    window.updateAssessmentStatus = async function(assessmentId, newStatus) {
        const menu = document.getElementById('status-dropdown-menu');
        if (menu) menu.classList.add('hidden');

        try {
            const response = await fetch('<?= BASE_URL ?>/api/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    assessment_id: assessmentId,
                    status: newStatus
                })
            });

            if (!response.ok) throw new Error('Status update failed');
            const data = await response.json();

            if (data.success) {
                const headerBadge = document.getElementById('header-status-badge');
                if (headerBadge) {
                    headerBadge.textContent = data.status_label;
                    headerBadge.className = 'px-2.5 py-0.5 rounded text-xs font-bold border ' + data.status_badge_class;
                }

                const slaContainer = document.getElementById('sla-badge-container');
                if (slaContainer && data.sla && data.sla.badge_html) {
                    slaContainer.innerHTML = data.sla.badge_html;
                }

                const saveStatus = document.getElementById('milestone-save-status');
                if (saveStatus) {
                    saveStatus.classList.remove('hidden');
                    setTimeout(() => saveStatus.classList.add('hidden'), 2500);
                }
            }
        } catch (err) {
            console.error('Status update error:', err);
            alert('Could not update status. Please refresh and try again.');
        }
    };

    window.startTracker = async function(assessmentId) {
        try {
            const response = await fetch('<?= BASE_URL ?>/api/update_tracker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'start',
                    assessment_id: assessmentId
                })
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to start tracker.');
            }
        } catch (err) {
            console.error('startTracker error:', err);
            alert('An error occurred while starting the tracker.');
        }
    };

    window.resumeTracker = async function(assessmentId) {
        try {
            const response = await fetch('<?= BASE_URL ?>/api/update_tracker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'resume',
                    assessment_id: assessmentId
                })
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to resume tracker.');
            }
        } catch (err) {
            console.error('resumeTracker error:', err);
            alert('An error occurred while resuming the tracker.');
        }
    };

    window.setTrackerStatus = async function(assessmentId, newStatus) {
        const menu = document.getElementById('status-dropdown-menu');
        if (menu) menu.classList.add('hidden');

        try {
            const response = await fetch('<?= BASE_URL ?>/api/update_tracker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'set_status',
                    assessment_id: assessmentId,
                    status: newStatus
                })
            });
            const data = await response.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to update tracker status.');
            }
        } catch (err) {
            console.error('setTrackerStatus error:', err);
            alert('An error occurred while updating tracker status.');
        }
    };
</script>

<?php require __DIR__ . '/email_modal.php'; ?>