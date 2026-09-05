<?php

/**
 * United Five Construction — Start New Client Pre-Assessment
 * Redesigned landing page matching the UFC Master Framework reference UI.
 * Includes: live project-name check, tier selector from DB, all original fields.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/project_check.php';

requireLogin();
$currentUser = getCurrentUser();
$pdo = getDbConnection();

// Load tiers from DB
$tiers = $pdo->query("SELECT id, name, description, color FROM tiers ORDER BY sort_order ASC")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $clientName     = trim($_POST['client_name']     ?? '');
        $clientEmail    = trim($_POST['client_email']    ?? '');
        $clientPhone    = trim($_POST['client_phone']    ?? '');
        $projectName    = trim($_POST['project_name']    ?? '');
        $projectAddress = trim($_POST['project_address'] ?? '');
        $tierId         = (int)($_POST['tier_id']        ?? 0) ?: null;

        if (empty($clientName) || empty($clientEmail) || empty($projectName)) {
            $error = 'Client name, email, and project name are required.';
        } elseif (checkProjectExists($pdo, $projectName)) {
            echo renderProjectExistsModal();
            $error = 'A project with that name already exists.';
        } else {
            $assessmentNumber = generateAssessmentNumber();
            $stmt = $pdo->prepare("
                INSERT INTO assessments
                    (assessment_number, client_name, client_email, client_phone,
                     project_name, project_address, tier_id, assessor_id, current_phase, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'IN_PROGRESS')
            ");
            $stmt->execute([
                $assessmentNumber,
                $clientName,
                $clientEmail,
                $clientPhone,
                $projectName,
                $projectAddress,
                $tierId,
                $currentUser['id'],
            ]);
            $newId = (int)$pdo->lastInsertId();

            logAudit($newId, 'ASSESSMENT_CREATED', [
                'client_name'  => $clientName,
                'project_name' => $projectName,
            ]);

            setFlashMessage('success', "New lead created for {$clientName} — {$projectName}. Beginning Phase 1.");
            header('Location: ' . BASE_URL . '/assessment/question.php?id=' . $newId . '&q=1.1');
            exit;
        }
    }
}

// ── Phase overview boxes (from questions.php global $PHASES if available) ──
$phaseBoxes = '';
$phaseLabels = [
    'DOCUMENT READINESS',
    'FINANCIAL CAPACITY AND CLIENT COMMITMENT',
    'PROPERTY AND LEGAL STANDING',
    'UFC DUE DILIGENCE AND FIT',
];
for ($i = 1; $i <= 4; $i++) {
    $label = $phaseLabels[$i - 1] ?? "Phase {$i}";
    $phaseBoxes .= "
        <div class='flex flex-col items-center'>
            <div class='bg-[#1a3a5c] rounded-md w-full py-2 flex items-center justify-center font-bold text-[#c9a84c] text-sm tracking-widest'>P{$i}</div>
            <div class='text-[10px] text-slate-400 mt-1 text-center leading-tight'>{$label}</div>
        </div>";
}

$pageTitle = 'New Client Pre-Assessment — UFC';
require_once __DIR__ . '/../components/header.php';
?>

<!-- ── Full-height centered screen ───────────────────────────────────────── -->
<div class="min-h-[calc(100vh-120px)] flex flex-col items-center justify-center py-8 px-4">
    <div class="w-full max-w-[750px]">
        <div class="bg-[#0d1f3c] border border-[#1a3a5c] rounded-xl p-8 sm:p-10 shadow-[0_20px_60px_rgba(0,0,0,0.5)] text-center">

            <!-- Brand header -->
            <div class="text-[11px] font-bold tracking-[0.3em] text-[#8b0000] mb-3 uppercase">
                UNITED FIVE CONSTRUCTION — CONFIDENTIAL
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-wide mb-0">
                THE UFC
            </h1>
            <p class="text-lg font-bold text-[#c9a84c] tracking-[0.15em] mb-3 uppercase">
                MASTER FRAMEWORK
            </p>
            <div class="w-14 h-[3px] bg-[#8b0000] mx-auto mb-8"></div>

            <?php if ($error && !str_contains($error, 'already exists')): ?>
                <div class="text-[13px] text-[#f87171] bg-[#f87171]/10 border border-[#f87171]/25 rounded-lg py-3 px-4 mb-6 text-left leading-relaxed">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Fields grid -->
            <form method="POST" novalidate id="start-form">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left w-full">

                    <!-- CLIENT NAME -->
                    <div>
                        <label for="client-name" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            CLIENT NAME
                        </label>
                        <input
                            class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors placeholder-[#4b5563]"
                            type="text"
                            id="client-name"
                            name="client_name"
                            placeholder="Enter client name..."
                            value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>"
                            autocomplete="off"
                            minlength="2"
                            maxlength="150"
                            required>
                        <div id="client-name-err" class="text-[#f87171] text-[12px] mt-1 hidden"></div>
                    </div>

                    <!-- CONTACT / PHONE -->
                    <div>
                        <label for="contact-phone" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            CONTACT
                        </label>
                        <input
                            class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors placeholder-[#4b5563]"
                            type="text"
                            id="contact-phone"
                            name="client_phone"
                            placeholder="Enter contact person or phone..."
                            value="<?= htmlspecialchars($_POST['client_phone'] ?? '') ?>"
                            autocomplete="off"
                            maxlength="100">
                    </div>

                    <!-- EMAIL -->
                    <div>
                        <label for="email-addr" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            EMAIL
                        </label>
                        <input
                            class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors placeholder-[#4b5563]"
                            type="email"
                            id="email-addr"
                            name="client_email"
                            placeholder="Enter contact email..."
                            value="<?= htmlspecialchars($_POST['client_email'] ?? '') ?>"
                            autocomplete="off"
                            maxlength="150"
                            required>
                        <div id="email-addr-err" class="text-[#f87171] text-[12px] mt-1 hidden"></div>
                    </div>

                    <!-- PROJECT NAME (with live check) -->
                    <div>
                        <label for="proj-name" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            PROJECT NAME
                        </label>
                        <div class="relative">
                            <input
                                class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors placeholder-[#4b5563] pr-10"
                                type="text"
                                id="proj-name"
                                name="project_name"
                                placeholder="Enter project or deal identifier..."
                                value="<?= htmlspecialchars($_POST['project_name'] ?? '') ?>"
                                autocomplete="off"
                                minlength="3"
                                maxlength="255"
                                required>
                            <!-- Status icon -->
                            <span id="proj-status-icon"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-base hidden pointer-events-none">
                            </span>
                        </div>
                        <!-- Unified feedback: validating / taken / available -->
                        <div id="proj-name-feedback" class="text-[12px] mt-1 hidden"></div>
                    </div>

                    <!-- PROJECT ADDRESS -->
                    <div class="md:col-span-2">
                        <label for="proj-address" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            PROJECT ADDRESS
                        </label>
                        <input
                            class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors placeholder-[#4b5563]"
                            type="text"
                            id="proj-address"
                            name="project_address"
                            placeholder="Enter project site address..."
                            value="<?= htmlspecialchars($_POST['project_address'] ?? '') ?>"
                            autocomplete="off"
                            maxlength="500">
                    </div>

                    <!-- TIER SELECTOR -->
                    <div class="md:col-span-2">
                        <label for="tier-id" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 uppercase font-bold">
                            DEAL TIER
                        </label>
                        <select
                            id="tier-id"
                            name="tier_id"
                            class="w-full px-4 py-3 rounded-md border border-[#1a3a5c] bg-[#060f1e] text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors appearance-none">
                            <option value="" class="text-[#6b7280]">Select deal tier...</option>
                            <?php foreach ($tiers as $tier): ?>
                                <option
                                    value="<?= (int)$tier['id'] ?>"
                                    <?= (int)($_POST['tier_id'] ?? 0) === (int)$tier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-1.5 text-[11px] text-[#6b7280]" id="tier-desc"></div>
                    </div>

                </div><!-- /grid -->

                <!-- NEXT button -->
                <button
                    type="submit"
                    class="w-full mt-8 py-4 rounded-md border-none font-bold tracking-[0.25em] text-[14px] uppercase transition-all focus:outline-none focus:ring-2 focus:ring-[#c9a84c] disabled:opacity-40 disabled:cursor-not-allowed"
                    id="start-btn"
                    style="background:#1a3a5c;color:#94a3b8;"
                    disabled>
                    NEXT &rarr;
                </button>

            </form>

            <!-- Phase overview grid -->
            <div class="grid grid-cols-4 gap-2 mt-8">
                <?= $phaseBoxes ?>
            </div>

        </div><!-- /card -->
    </div>
</div>

<!-- ── Tier descriptions (JSON island for JS) ──────────────────────────── -->
<script>
    const TIER_DATA = <?= json_encode(
                            array_column($tiers, null, 'id'),
                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                        ) ?>;
</script>

<script>
    (function() {
        /* ── DOM refs ─────────────────────────────────────────────────────── */
        const clientName = document.getElementById('client-name');
        const emailAddr = document.getElementById('email-addr');
        const projName = document.getElementById('proj-name');
        const projFb = document.getElementById('proj-name-feedback');
        const projIcon = document.getElementById('proj-status-icon');
        const startBtn = document.getElementById('start-btn');
        const tierSel = document.getElementById('tier-id');
        const tierDesc = document.getElementById('tier-desc');

        /* ── State ────────────────────────────────────────────────────────── */
        let projAvailable = (projName.value.trim().length >= 3) ? null : false;
        // null = "not yet checked"  false = "taken / error"  true = "available"

        /* ── Button style helpers ─────────────────────────────────────────── */
        function enableBtn() {
            startBtn.disabled = false;
            startBtn.style.background = '#8b0000';
            startBtn.style.color = '#fff';
        }

        function disableBtn() {
            startBtn.disabled = true;
            startBtn.style.background = '#1a3a5c';
            startBtn.style.color = '#94a3b8';
        }

        /* ── Validation gate ──────────────────────────────────────────────── */
        function checkCanSubmit() {
            const nameOk = clientName.value.trim().length >= 2;
            const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddr.value.trim());
            const projOk = projAvailable === true;
            if (nameOk && emailOk && projOk) {
                enableBtn();
            } else {
                disableBtn();
            }
        }

        /* ── Field error helpers ──────────────────────────────────────────── */
        function showErr(el, msg) {
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        function clearErr(el) {
            el.textContent = '';
            el.classList.add('hidden');
        }

        /* ── Client name live validation ──────────────────────────────────── */
        clientName.addEventListener('input', function() {
            const v = this.value.trim();
            const errEl = document.getElementById('client-name-err');
            if (v.length > 0 && v.length < 2) showErr(errEl, 'At least 2 characters required.');
            else clearErr(errEl);
            checkCanSubmit();
        });

        /* ── Email live validation ────────────────────────────────────────── */
        emailAddr.addEventListener('input', function() {
            const v = this.value.trim();
            const errEl = document.getElementById('email-addr-err');
            if (v.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))
                showErr(errEl, 'Please enter a valid email address.');
            else clearErr(errEl);
            checkCanSubmit();
        });

        /* ── Tier description display ─────────────────────────────────────── */
        tierSel.addEventListener('change', function() {
            const id = parseInt(this.value, 10);
            if (TIER_DATA[id] && TIER_DATA[id].description) {
                tierDesc.textContent = TIER_DATA[id].description;
            } else {
                tierDesc.textContent = '';
            }
        });

        /* ── Project-name live check ──────────────────────────────────────── */
        let debounceTimer = null;

        function setFeedback(text, colorClass, icon) {
            projFb.textContent = text;
            projFb.className = 'text-[12px] mt-1 ' + colorClass;
            projFb.classList.remove('hidden');
            projIcon.textContent = icon;
            projIcon.classList.remove('hidden');
        }

        function clearFeedback() {
            projFb.textContent = '';
            projFb.classList.add('hidden');
            projIcon.textContent = '';
            projIcon.classList.add('hidden');
        }

        projName.addEventListener('input', function() {
            const val = this.value.trim();
            clearTimeout(debounceTimer);

            if (val.length < 3) {
                projAvailable = false;
                clearFeedback();
                setFeedback('Project name must be at least 3 characters', 'text-red-400', '❌');
                checkCanSubmit();
                return;
            }

            // Show "checking…" immediately
            setFeedback('Checking availability…', 'text-[#c9a84c]', '⏳');
            projAvailable = null;
            disableBtn();

            debounceTimer = setTimeout(function() {
                fetch('<?= BASE_URL ?>/api/check_project_name.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            name: val
                        })
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.available) {
                            projAvailable = true;
                            setFeedback('✓ Project name is available', 'text-[#4ade80]', '✅');
                        } else {
                            projAvailable = false;
                            setFeedback('✗ This project name is already taken', 'text-[#f87171]', '❌');
                        }
                        checkCanSubmit();
                    })
                    .catch(function() {
                        projAvailable = false;
                        setFeedback('Could not verify — check your connection', 'text-[#f87171]', '⚠️');
                        checkCanSubmit();
                    });
            }, 480);
        });

        /* ── Trigger initial state for pre-filled values ──────────────────── */
        if (projName.value.trim().length >= 3) {
            projName.dispatchEvent(new Event('input'));
        }
        clientName.dispatchEvent(new Event('input'));
        emailAddr.dispatchEvent(new Event('input'));
    })();
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>