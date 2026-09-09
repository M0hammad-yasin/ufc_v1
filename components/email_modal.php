<?php
/**
 * components/email_modal.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Reusable Communication & Lead Generation Modal for UFC v1.
 * Supports:
 * 1. Lead Generation Report:
 *    - Choice of Phase: All Phases (1–4) or specific Phase 1 to 4
 *    - Choice of Content: Phase Results, Itemized Questions & Answers, or both
 *    - Destination: Send to any given email address
 *    - PDF Export: Instant Dompdf PDF download with exact selected phase options
 * 2. Requirements Notice Letter
 * 3. Custom Email
 */
if (!isset($assessment) || empty($assessment['id'])) {
    return;
}
$assessmentId   = (int)$assessment['id'];
$defaultClient  = htmlspecialchars($assessment['client_email'] ?? '');
$defaultPhase   = (int)($assessment['current_phase'] ?? 1);
?>

<!-- Reusable Email / Lead Generation Modal Container -->
<div id="ufc-email-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="p-5 bg-gradient-to-r from-[#0d1f3c] via-[#122849] to-[#0a172c] border-b border-[#1e3e68] flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-[#c9a84c]/20 border border-[#c9a84c]/40 flex items-center justify-center text-[#c9a84c]">
                    <i class="fa-solid fa-paper-plane text-sm"></i>
                </div>
                <div>
                    <h3 class="font-serif text-lg font-bold text-white leading-tight">
                        Lead Report &amp; Communications
                    </h3>
                    <p class="text-[11px] text-slate-400">
                        Assessment Ref: <span class="font-mono text-slate-300 font-bold"><?= htmlspecialchars($assessment['assessment_number']) ?></span>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeEmailModal()" class="text-slate-400 hover:text-white transition-colors cursor-pointer text-lg p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Form Body -->
        <form id="ufc-email-form" onsubmit="handleEmailFormSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">

            <!-- Email Action Type -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Communication / Report Type *</label>
                <select name="action" id="email-action-type" onchange="toggleEmailTypeFields()" required
                        class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    <option value="send_lead_summary">Lead Generation &amp; Qualification Report</option>
                    <option value="send_letter">Requirements / Notice Letter (Phase <?= $defaultPhase ?>)</option>
                    <option value="send_custom">Custom Note / Notification</option>
                </select>
            </div>

            <!-- LEAD GENERATION OPTIONS PANEL (Choice 1 to 4 and All Phases, Results & Answers) -->
            <div id="lead-options-panel" class="bg-[#060f1e] border border-[#1e3e68] rounded-xl p-4 space-y-3.5">
                <div class="flex items-center justify-between border-b border-[#1e3e68] pb-2">
                    <span class="text-xs font-bold text-[#c9a84c] uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Lead Report Configuration</span>
                    </span>
                    <span class="text-[10px] text-slate-400">Phases &amp; Data Selection</span>
                </div>

                <!-- Choice: 1 to 4 and All Phases -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                        Select Phase Scope *
                    </label>
                    <select name="phase_choice" id="lead-phase-choice"
                            class="w-full px-3.5 py-2.5 bg-[#0d1f3c] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors font-medium">
                        <option value="all" selected>All Phases (Phases 1 to 4) — Complete Assessment</option>
                        <option value="1">Phase 1: Document Readiness</option>
                        <option value="2">Phase 2: Financial Capacity and Client Commitment</option>
                        <option value="3">Phase 3: Property, Scope &amp; Legal</option>
                        <option value="4">Phase 4: UFC Due Diligence</option>
                    </select>
                </div>

                <!-- Choice: Results and/or Answers -->
                <div class="space-y-2 pt-1">
                    <label class="block text-xs font-semibold text-slate-300">Report Inclusions</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-center gap-2.5 p-2.5 bg-[#0d1f3c] border border-[#1e3e68] rounded-lg text-xs text-slate-200 cursor-pointer hover:border-slate-500 transition-colors">
                            <input type="checkbox" name="include_results" id="lead-include-results" value="1" checked
                                   class="w-4 h-4 rounded text-[#c9a84c] focus:ring-[#c9a84c] bg-[#060f1e] border-slate-600">
                            <div>
                                <span class="font-bold text-white block text-[11px]">Phase Results</span>
                                <span class="text-[10px] text-slate-400 block">Status, score % &amp; gates</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-2.5 bg-[#0d1f3c] border border-[#1e3e68] rounded-lg text-xs text-slate-200 cursor-pointer hover:border-slate-500 transition-colors">
                            <input type="checkbox" name="include_answers" id="lead-include-answers" value="1" checked
                                   class="w-4 h-4 rounded text-[#c9a84c] focus:ring-[#c9a84c] bg-[#060f1e] border-slate-600">
                            <div>
                                <span class="font-bold text-white block text-[11px]">Itemized Answers</span>
                                <span class="text-[10px] text-slate-400 block">Questions &amp; responses</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Single Phase Selector (For Requirements Letter only) -->
            <div id="field-phase-number" class="hidden">
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Target Deficient Phase</label>
                <select name="phase_number" class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    <option value="1" <?= $defaultPhase === 1 ? 'selected' : '' ?>>Phase 1: Document Readiness</option>
                    <option value="2" <?= $defaultPhase === 2 ? 'selected' : '' ?>>Phase 2: Financial Capacity</option>
                    <option value="3" <?= $defaultPhase === 3 ? 'selected' : '' ?>>Phase 3: Property &amp; Legal</option>
                    <option value="4" <?= $defaultPhase === 4 ? 'selected' : '' ?>>Phase 4: UFC Due Diligence</option>
                </select>
            </div>

            <!-- Given Recipient Email Address -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                    <span>Send to Given Address *</span>
                    <span class="text-[10px] text-slate-400 font-normal ml-1">(Client, partner, lender, or team)</span>
                </label>
                <input type="email" name="recipient_email" id="email-recipient" required
                       value="<?= $defaultClient ?>"
                       placeholder="Enter recipient email address..."
                       class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
            </div>

            <!-- Subject Field (For Custom Email) -->
            <div id="field-custom-subject" class="hidden">
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email Subject *</label>
                <input type="text" name="subject" placeholder="e.g. Follow-up regarding UFC Assessment"
                       class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
            </div>

            <!-- Message / Custom Note Area -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5" id="label-custom-note">Custom Assessor Note (Optional)</label>
                <textarea name="custom_note" id="email-custom-note" rows="2"
                          placeholder="Add any specific context or instructions for the recipient..."
                          class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors"></textarea>
            </div>

            <!-- Response Alert Banner -->
            <div id="email-modal-alert" class="hidden p-3 rounded-lg text-xs font-semibold"></div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-[#1e3e68] flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <!-- PDF Export Button for Lead Generation -->
                <div id="lead-pdf-export-wrapper">
                    <button type="button" onclick="downloadLeadPdf()"
                            class="w-full sm:w-auto px-3.5 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded-lg border border-[#1e3e68] transition-all flex items-center justify-center gap-2 cursor-pointer"
                            title="Export selected phase results and answers to PDF using Dompdf">
                        <i class="fa-solid fa-file-pdf text-[#c9a84c]"></i>
                        <span>Download Lead PDF</span>
                    </button>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeEmailModal()" 
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" id="btn-send-email-submit"
                            class="px-5 py-2 bg-gradient-to-r from-[#c9a84c] to-[#d6b85e] hover:from-[#d6b85e] hover:to-[#e2c773] text-[#060f1e] text-xs font-bold rounded-lg shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Send to Given Address</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openEmailModal(defaultAction) {
    const modal = document.getElementById('ufc-email-modal');
    const select = document.getElementById('email-action-type');
    if (defaultAction && select) {
        select.value = defaultAction;
    }
    toggleEmailTypeFields();
    if (modal) modal.classList.remove('hidden');
}

function closeEmailModal() {
    const modal = document.getElementById('ufc-email-modal');
    if (modal) modal.classList.add('hidden');
    const alert = document.getElementById('email-modal-alert');
    if (alert) alert.classList.add('hidden');
}

function toggleEmailTypeFields() {
    const action = document.getElementById('email-action-type').value;
    const leadPanel = document.getElementById('lead-options-panel');
    const leadPdfBtn = document.getElementById('lead-pdf-export-wrapper');
    const phaseField = document.getElementById('field-phase-number');
    const subjectField = document.getElementById('field-custom-subject');
    const noteLabel = document.getElementById('label-custom-note');

    if (action === 'send_lead_summary') {
        if (leadPanel) leadPanel.classList.remove('hidden');
        if (leadPdfBtn) leadPdfBtn.classList.remove('hidden');
        if (phaseField) phaseField.classList.add('hidden');
        if (subjectField) subjectField.classList.add('hidden');
        if (noteLabel) noteLabel.textContent = 'Custom Assessor Note (Optional)';
    } else if (action === 'send_custom') {
        if (leadPanel) leadPanel.classList.add('hidden');
        if (leadPdfBtn) leadPdfBtn.classList.add('hidden');
        if (phaseField) phaseField.classList.add('hidden');
        if (subjectField) subjectField.classList.remove('hidden');
        if (noteLabel) noteLabel.textContent = 'Message Body *';
    } else { // send_letter
        if (leadPanel) leadPanel.classList.add('hidden');
        if (leadPdfBtn) leadPdfBtn.classList.add('hidden');
        if (phaseField) phaseField.classList.remove('hidden');
        if (subjectField) subjectField.classList.add('hidden');
        if (noteLabel) noteLabel.textContent = 'Custom Assessor Note (Optional)';
    }
}

function downloadLeadPdf() {
    const assessmentId = <?= $assessmentId ?>;
    const phase = document.getElementById('lead-phase-choice') ? document.getElementById('lead-phase-choice').value : 'all';
    const results = (document.getElementById('lead-include-results') && document.getElementById('lead-include-results').checked) ? 1 : 0;
    const answers = (document.getElementById('lead-include-answers') && document.getElementById('lead-include-answers').checked) ? 1 : 0;

    const url = '<?= BASE_URL ?>/api/export_lead_pdf.php?id=' + encodeURIComponent(assessmentId) +
                '&phase=' + encodeURIComponent(phase) +
                '&results=' + encodeURIComponent(results) +
                '&answers=' + encodeURIComponent(answers) +
                '&download=1';
    window.open(url, '_blank');
}

async function handleEmailFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const alert = document.getElementById('email-modal-alert');
    const btn = document.getElementById('btn-send-email-submit');

    const formData = new FormData(form);
    const data = {};
    formData.forEach((val, key) => data[key] = val);

    // Ensure boolean checkboxes for lead report
    data.include_results = document.getElementById('lead-include-results') ? document.getElementById('lead-include-results').checked : true;
    data.include_answers = document.getElementById('lead-include-answers') ? document.getElementById('lead-include-answers').checked : true;

    // If custom action, map custom_note to message
    if (data.action === 'send_custom') {
        data.message = data.custom_note;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> <span>Sending...</span>';
    }

    try {
        const res = await fetch('<?= BASE_URL ?>/api/send_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (alert) {
            alert.classList.remove('hidden');
            if (json.success) {
                alert.className = 'p-3 rounded-lg text-xs font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-500';
                alert.textContent = json.message || 'Report successfully dispatched.';
                setTimeout(() => {
                    closeEmailModal();
                }, 2200);
            } else {
                alert.className = 'p-3 rounded-lg text-xs font-semibold bg-red-950/80 text-red-300 border border-red-500';
                alert.textContent = json.error || 'Failed to send communication.';
            }
        }
    } catch (err) {
        if (alert) {
            alert.classList.remove('hidden');
            alert.className = 'p-3 rounded-lg text-xs font-semibold bg-red-950/80 text-red-300 border border-red-500';
            alert.textContent = 'Network error. Could not reach server.';
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> <span>Send to Given Address</span>';
        }
    }
}
</script>
