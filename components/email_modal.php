<?php
/**
 * components/email_modal.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Reusable Email Modal for UFC v1.
 * Allows assessors to send Requirements Letters, Lead Contact Summaries, or Custom emails.
 */
if (!isset($assessment) || empty($assessment['id'])) {
    return;
}
$assessmentId   = (int)$assessment['id'];
$defaultClient  = htmlspecialchars($assessment['client_email'] ?? '');
$defaultPhase   = (int)($assessment['current_phase'] ?? 1);
?>

<!-- Reusable Email Modal Container -->
<div id="ufc-email-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="p-5 bg-gradient-to-r from-[#0d1f3c] via-[#122849] to-[#0a172c] border-b border-[#1e3e68] flex items-center justify-between">
            <h3 class="font-serif text-lg font-bold text-white flex items-center gap-2.5">
                <i class="fa-solid fa-paper-plane text-[#c9a84c]"></i>
                <span>Send Communication Email</span>
            </h3>
            <button type="button" onclick="closeEmailModal()" class="text-slate-400 hover:text-white transition-colors cursor-pointer text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Form Body -->
        <form id="ufc-email-form" onsubmit="handleEmailFormSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">

            <!-- Email Action Type -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email Communication Type *</label>
                <select name="action" id="email-action-type" onchange="toggleEmailTypeFields()" required
                        class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    <option value="send_letter">Requirements / Notice Letter (Phase <?= $defaultPhase ?>)</option>
                    <option value="send_lead_summary">Client Lead &amp; Contact Form Summary</option>
                    <option value="send_custom">Custom Note / Email</option>
                </select>
            </div>

            <!-- Recipient Email -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Recipient Email *</label>
                <input type="email" name="recipient_email" id="email-recipient" required
                       value="<?= $defaultClient ?>"
                       placeholder="e.g. client@domain.com"
                       class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
            </div>

            <!-- Phase Number (For Requirements Letter) -->
            <div id="field-phase-number">
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Target Phase Number</label>
                <select name="phase_number" class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    <option value="1" <?= $defaultPhase === 1 ? 'selected' : '' ?>>Phase 1: Document Readiness</option>
                    <option value="2" <?= $defaultPhase === 2 ? 'selected' : '' ?>>Phase 2: Financial Capacity</option>
                    <option value="3" <?= $defaultPhase === 3 ? 'selected' : '' ?>>Phase 3: Property &amp; Legal</option>
                    <option value="4" <?= $defaultPhase === 4 ? 'selected' : '' ?>>Phase 4: UFC Due Diligence</option>
                </select>
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
                <textarea name="custom_note" id="email-custom-note" rows="3"
                          placeholder="Add any specific message or instructions for the client..."
                          class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white focus:outline-none focus:border-[#c9a84c] transition-colors"></textarea>
            </div>

            <!-- Response Alert Banner -->
            <div id="email-modal-alert" class="hidden p-3 rounded-lg text-xs font-semibold"></div>

            <!-- Form Actions -->
            <div class="pt-4 border-t border-[#1e3e68] flex items-center justify-end gap-3">
                <button type="button" onclick="closeEmailModal()" 
                        class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-300 text-xs font-semibold rounded-lg border border-[#1e3e68] transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-send-email-submit"
                        class="px-5 py-2 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-xs font-bold rounded-lg shadow-lg transition-all flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Send Email Now</span>
                </button>
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
    const phaseField = document.getElementById('field-phase-number');
    const subjectField = document.getElementById('field-custom-subject');
    const noteLabel = document.getElementById('label-custom-note');

    if (action === 'send_custom') {
        if (phaseField) phaseField.classList.add('hidden');
        if (subjectField) subjectField.classList.remove('hidden');
        if (noteLabel) noteLabel.textContent = 'Message Body *';
    } else {
        if (phaseField) phaseField.classList.remove('hidden');
        if (subjectField) subjectField.classList.add('hidden');
        if (noteLabel) noteLabel.textContent = 'Custom Assessor Note (Optional)';
    }
}

async function handleEmailFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const alert = document.getElementById('email-modal-alert');
    const btn = document.getElementById('btn-send-email-submit');

    const formData = new FormData(form);
    const data = {};
    formData.forEach((val, key) => data[key] = val);

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
                alert.textContent = json.message || 'Email dispatched successfully.';
                setTimeout(() => {
                    closeEmailModal();
                }, 1800);
            } else {
                alert.className = 'p-3 rounded-lg text-xs font-semibold bg-red-950/80 text-red-300 border border-red-500';
                alert.textContent = json.error || 'Failed to send email.';
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
            btn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i> <span>Send Email Now</span>';
        }
    }
}
</script>
