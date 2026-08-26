<?php
/**
 * United Five Construction - Start New Client Pre-Assessment
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';

requireLogin();
$currentUser = getCurrentUser();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $clientName = trim($_POST['client_name'] ?? '');
        $clientEmail = trim($_POST['client_email'] ?? '');
        $clientPhone = trim($_POST['client_phone'] ?? '');
        $projectAddress = trim($_POST['project_address'] ?? '');
        $projectType = trim($_POST['project_type'] ?? 'Commercial Renovation');
        $estimatedBudget = (float)($_POST['estimated_budget'] ?? 0);

        if (empty($clientName) || empty($clientEmail) || empty($projectAddress)) {
            $error = 'Client name, email, and project address are required.';
        } else {
            $pdo = getDbConnection();
            $assessmentNumber = generateAssessmentNumber();
            $stmt = $pdo->prepare("
                INSERT INTO assessments 
                (assessment_number, client_name, client_email, client_phone, project_address, project_type, estimated_budget, assessor_id, current_phase, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'IN_PROGRESS')
            ");
            $stmt->execute([
                $assessmentNumber,
                $clientName,
                $clientEmail,
                $clientPhone,
                $projectAddress,
                $projectType,
                $estimatedBudget,
                $currentUser['id']
            ]);
            $newAssessmentId = (int)$pdo->lastInsertId();

            logAudit($newAssessmentId, 'ASSESSMENT_CREATED', [
                'client_name' => $clientName,
                'project_address' => $projectAddress
            ]);

            setFlashMessage('success', "New lead created for {$clientName}. Beginning Phase 1: Document Readiness.");
            header("Location: /ufc_v1/assessment/question.php?id={$newAssessmentId}&q=1.1");
            exit;
        }
    }
}

$pageTitle = 'New Client Pre-Assessment — UFC';
require_once __DIR__ . '/../components/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="font-serif text-2xl font-bold text-slate-100">Intake New Client Lead</h1>
        <p class="text-sm text-slate-400 mt-1">Initiate the 4-phase sequential pre-assessment gate for United Five Construction.</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded bg-red-900/50 border border-red-500 text-red-200 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-6 sm:p-8 shadow-xl">
        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Client / Entity Name <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="client_name" required placeholder="e.g. 450 Lexington Properties LLC" 
                           value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Client Email Address <span class="text-red-400">*</span>
                    </label>
                    <input type="email" name="client_email" required placeholder="owner@entity.com" 
                           value="<?= htmlspecialchars($_POST['client_email'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Client Contact Phone
                    </label>
                    <input type="text" name="client_phone" placeholder="(212) 555-0199" 
                           value="<?= htmlspecialchars($_POST['client_phone'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Project Site Address <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="project_address" required placeholder="e.g. 450 Lexington Ave, New York, NY 10017" 
                           value="<?= htmlspecialchars($_POST['project_address'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Project Type
                    </label>
                    <select name="project_type" class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 focus:outline-none focus:border-[#c9a84c]">
                        <option value="Commercial Renovation">Commercial Renovation</option>
                        <option value="Residential Gut Rehab">Residential Gut Rehab</option>
                        <option value="New Building / Ground Up">New Building / Ground Up</option>
                        <option value="Retail / Restaurant Fit-out">Retail / Restaurant Fit-out</option>
                        <option value="Institutional / Office Interior">Institutional / Office Interior</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Client Stated Budget ($)
                    </label>
                    <input type="number" step="1000" name="estimated_budget" placeholder="1000000" 
                           value="<?= htmlspecialchars($_POST['estimated_budget'] ?? '') ?>"
                           class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                </div>
            </div>

            <div class="pt-4 border-t border-[#1e3e68] flex items-center justify-between">
                <a href="/ufc_v1/admin/assessments.php" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-sm rounded shadow transition-all flex items-center gap-2">
                    <span>Begin Phase 1 Gate</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
