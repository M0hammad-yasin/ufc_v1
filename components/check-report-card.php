<?php

/**
 * United Five Construction - Reusable "Check Report" Milestone & SLA Tracker Component
 *
 * Requirements:
 * 1. Created By ("kis ny bnaya tha")
 * 2. Last Updated By ("kis ny last update kia")
 * 3. Current Status & Phase ("current status kya hai")
 * 4. Action Area & 2-Week Phase 1 SLA Alert Indicator (Yellow Blink Week 1, Red Blink Week 2)
 * 5. Three Interactive Checkboxes:
 *    - Pre-Assessment
 *    - Client Meetup
 *    - Build Proposal
 */

if (!isset($assessment) || empty($assessment['id'])) {
    return;
}

$sla = getAssessmentSlaStatus($assessment);
$status = $assessment['status'] ?? 'IN_PROGRESS';
$creatorName = !empty($assessment['assessor_name']) ? $assessment['assessor_name'] : 'System';
$updaterName = !empty($assessment['last_updated_by_name']) ? $assessment['last_updated_by_name'] : $creatorName;

$chkAssessment        = (bool)($assessment['checkpoint_pre_assessment'] ?? 0);
$chkWalkThrough       = (bool)($assessment['checkpoint_client_meetup'] ?? 0);
$chkProposalSubmission = (bool)($assessment['checkpoint_build_proposal'] ?? 0);
$chkFinalBid          = (bool)($assessment['checkpoint_final_bid'] ?? 0);

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
                <!-- SLA Notification Badge Container -->
                <div id="sla-badge-container">
                    <?= $sla['badge_html'] ?>
                </div>

                <!-- Primary Action / Status Dropdown Area -->
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
                    <div id="status-dropdown-menu" class="hidden absolute right-0 mt-2 w-60 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-2xl z-50 overflow-hidden py-1">
                        <div class="px-3.5 py-2 border-b border-[#1e3e68] text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            Set Assessment Outcome
                        </div>

                        <button type="button"
                            onclick="updateAssessmentStatus(<?= (int)$assessment['id'] ?>, 'PROCEED_TO_PROPOSAL')"
                            class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-emerald-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                            <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                            <div>
                                <div>Assessment Completed</div>
                                <div class="text-[10px] text-slate-400 font-normal">Proceed to proposal &amp; stop timer</div>
                            </div>
                        </button>

                        <button type="button"
                            onclick="updateAssessmentStatus(<?= (int)$assessment['id'] ?>, 'HOLD')"
                            class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-amber-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                            <i class="fa-solid fa-circle-pause text-amber-400 text-sm"></i>
                            <div>
                                <div>Aborted / On Hold</div>
                                <div class="text-[10px] text-slate-400 font-normal">Pause assessment &amp; stop timer</div>
                            </div>
                        </button>

                        <button type="button"
                            onclick="updateAssessmentStatus(<?= (int)$assessment['id'] ?>, 'NOT_A_FIT')"
                            class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-red-400 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                            <i class="fa-solid fa-circle-xmark text-red-400 text-sm"></i>
                            <div>
                                <div>Rejected / Not A Fit</div>
                                <div class="text-[10px] text-slate-400 font-normal">Mark lead as not fit &amp; stop timer</div>
                            </div>
                        </button>

                        <div class="border-t border-[#1e3e68] my-1"></div>

                        <button type="button" 
                                onclick="openEmailModal('send_letter')"
                                class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-blue-300 hover:bg-[#122849] flex items-center gap-2.5 transition-colors cursor-pointer">
                            <i class="fa-solid fa-envelope-open-text text-blue-400 text-sm"></i>
                            <div>
                                <div>Send Requirements Letter</div>
                                <div class="text-[10px] text-slate-400 font-normal">Email notice letter to client</div>
                            </div>
                        </button>

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

                        <a href="/ufc_v1/assessment/question.php?id=<?= (int)$assessment['id'] ?>"
                            class="w-full text-left px-3.5 py-2.5 text-xs font-semibold text-slate-300 hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                            <i class="fa-solid fa-play text-[#c9a84c] text-xs"></i>
                            <span>Action / Run Phase</span>
                        </a>
                    </div>
                </div>
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

    <!-- 3 Milestone Checkboxes Section -->
    <div class="p-6 bg-[#081528]/50">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-serif text-sm font-bold text-slate-200 uppercase tracking-wider">
                    Lead Qualification &amp; Conversion Milestones
                </h3>
                <p class="text-[11px] text-slate-400">Track key lifecycle checkpoints for this lead below. Changes save automatically.</p>
            </div>
            <span id="milestone-save-status" class="hidden text-xs font-semibold text-emerald-400 flex items-center gap-1">
                <i class="fa-solid fa-check"></i> Saved
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Assessment Checkbox -->
            <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all cursor-pointer <?= $chkAssessment ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68] hover:border-[#2a558c]' ?>">
                <div class="flex items-center h-5 mt-0.5">
                    <input type="checkbox"
                        id="chk-assessment"
                        data-checkpoint="assessment"
                        data-assessment-id="<?= (int)$assessment['id'] ?>"
                        <?= $chkAssessment ? 'checked' : '' ?>
                        class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 cursor-pointer transition-colors">
                </div>
                <div class="flex-1 select-none">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">1. Assessment</span>
                        <span id="badge-assessment" class="text-[10px] font-bold px-2 py-0.5 rounded <?= $chkAssessment ? 'bg-emerald-950 text-emerald-300 border border-emerald-600/60' : 'bg-slate-800 text-slate-400' ?>">
                            <?= $chkAssessment ? 'Completed' : 'Pending' ?>
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1 leading-snug">
                        Document readiness &amp; Phase 1 qualification gate verified.
                    </p>
                    <div id="time-assessment" class="text-[10px] text-slate-500 mt-2 font-mono">
                        <?= $assessment['checkpoint_pre_assessment_at'] ? 'Marked: ' . formatDate($assessment['checkpoint_pre_assessment_at'], 'M j, Y H:i') : 'Pending verification' ?>
                    </div>
                </div>
            </label>

            <!-- 2. Walk Through Checkbox -->
            <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all cursor-pointer <?= $chkWalkThrough ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68] hover:border-[#2a558c]' ?>">
                <div class="flex items-center h-5 mt-0.5">
                    <input type="checkbox"
                        id="chk-walk-through"
                        data-checkpoint="walk_through"
                        data-assessment-id="<?= (int)$assessment['id'] ?>"
                        <?= $chkWalkThrough ? 'checked' : '' ?>
                        class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 cursor-pointer transition-colors">
                </div>
                <div class="flex-1 select-none">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">2. Walk Through</span>
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

            <!-- 3. Proposal Submission Checkbox -->
            <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all cursor-pointer <?= $chkProposalSubmission ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68] hover:border-[#2a558c]' ?>">
                <div class="flex items-center h-5 mt-0.5">
                    <input type="checkbox"
                        id="chk-proposal-submission"
                        data-checkpoint="proposal_submission"
                        data-assessment-id="<?= (int)$assessment['id'] ?>"
                        <?= $chkProposalSubmission ? 'checked' : '' ?>
                        class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 cursor-pointer transition-colors">
                </div>
                <div class="flex-1 select-none">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">3. Proposal Submission</span>
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

            <!-- 4. Final Bid Checkbox -->
            <label class="group relative flex items-start gap-3.5 p-4 rounded-xl border transition-all cursor-pointer <?= $chkFinalBid ? 'bg-[#122849]/90 border-emerald-500/50 shadow-md' : 'bg-[#0a172c]/70 border-[#1e3e68] hover:border-[#2a558c]' ?>">
                <div class="flex items-center h-5 mt-0.5">
                    <input type="checkbox"
                        id="chk-final-bid"
                        data-checkpoint="final_bid"
                        data-assessment-id="<?= (int)$assessment['id'] ?>"
                        <?= $chkFinalBid ? 'checked' : '' ?>
                        class="w-4 h-4 rounded text-[#c9a84c] bg-[#060f1e] border-[#1e3e68] focus:ring-[#c9a84c] focus:ring-offset-0 cursor-pointer transition-colors">
                </div>
                <div class="flex-1 select-none">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-xs text-slate-100 group-hover:text-white transition-colors">4. Final Bid</span>
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
                    const response = await fetch('/ufc_v1/api/update_milestones.php', {
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
            const response = await fetch('/ufc_v1/api/update_status.php', {
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
</script>

<?php require __DIR__ . '/email_modal.php'; ?>