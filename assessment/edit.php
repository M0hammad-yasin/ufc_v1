<?php
/**
 * United Five Construction — Edit Assessment Details
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/project_check.php';

requireLogin();
$currentUser = getCurrentUser();
$pdo = getDbConnection();

$assessmentId = (int)($_GET['id'] ?? 0);
$assessment = getAssessmentDetails($assessmentId);

if (!$assessment) {
    setFlashMessage('danger', 'Assessment not found.');
    header("Location: /ufc_v1/admin/assessments.php");
    exit;
}

// Load tiers
$tiers = $pdo->query("SELECT id, name, description, color FROM tiers ORDER BY sort_order ASC")->fetchAll();

$error = '';
$success = '';

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
        $projectType    = trim($_POST['project_type']    ?? '');
        $estimatedBudget= !empty($_POST['estimated_budget']) ? (float)$_POST['estimated_budget'] : null;
        $tierId         = (int)($_POST['tier_id']        ?? 0) ?: null;

        if (empty($clientName) || empty($clientEmail) || empty($projectName)) {
            $error = 'Client name, email, and project name are required.';
        } elseif (checkProjectExists($pdo, $projectName, $assessmentId)) {
            $error = 'A project with that name already exists.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE assessments SET
                    client_name = ?,
                    client_email = ?,
                    client_phone = ?,
                    project_name = ?,
                    project_address = ?,
                    project_type = ?,
                    estimated_budget = ?,
                    tier_id = ?,
                    last_updated_by_user_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $clientName,
                $clientEmail,
                $clientPhone,
                $projectName,
                $projectAddress,
                $projectType,
                $estimatedBudget,
                $tierId,
                $currentUser['id'],
                $assessmentId
            ]);

            logAudit($assessmentId, 'ASSESSMENT_METADATA_UPDATED', [
                'client_name'  => $clientName,
                'project_name' => $projectName,
                'updated_by'   => $currentUser['name']
            ]);

            setFlashMessage('success', 'Assessment details updated successfully.');
            header("Location: /ufc_v1/admin/assessment.php?id=" . $assessmentId);
            exit;
        }
    }
}

$pageTitle = "Edit Assessment #{$assessment['assessment_number']} — {$assessment['client_name']}";
require_once __DIR__ . '/../components/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Top Breadcrumb Bar -->
    <div class="flex items-center justify-between">
        <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
           class="px-4 py-2 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded border border-[#1e3e68] transition-all flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Assessment</span>
        </a>
        <span class="font-mono text-xs text-[#c9a84c] font-bold bg-[#0d1f3c] px-3 py-1.5 rounded border border-[#1e3e68]">
            <?= htmlspecialchars($assessment['assessment_number']) ?>
        </span>
    </div>

    <!-- Edit Card -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-[#0d1f3c] via-[#122849] to-[#0a172c] border-b border-[#1e3e68]">
            <h1 class="font-serif text-2xl font-bold text-white flex items-center gap-3">
                <i class="fa-regular fa-pen-to-square text-[#c9a84c]"></i>
                <span>Edit Assessment Details</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Update client profile and project scope information.</p>
        </div>

        <form method="POST" action="" class="p-6 sm:p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-950/80 border border-red-500 rounded-lg text-red-200 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Client Details Section -->
            <div>
                <h3 class="text-xs font-bold text-[#c9a84c] uppercase tracking-wider mb-4 pb-1 border-b border-[#1e3e68] flex items-center gap-2">
                    <i class="fa-solid fa-user"></i>
                    <span>Client Information</span>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Client Name *</label>
                        <input type="text" name="client_name" required 
                               value="<?= htmlspecialchars($_POST['client_name'] ?? $assessment['client_name']) ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Client Email *</label>
                        <input type="email" name="client_email" required 
                               value="<?= htmlspecialchars($_POST['client_email'] ?? $assessment['client_email']) ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Client Phone / Contact</label>
                        <input type="text" name="client_phone" 
                               value="<?= htmlspecialchars($_POST['client_phone'] ?? $assessment['client_phone'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Assessment Tier</label>
                        <select name="tier_id" class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                            <option value="">-- Select Tier --</option>
                            <?php foreach ($tiers as $t): 
                                $selected = ((int)($assessment['tier_id'] ?? 0) === (int)$t['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $t['id'] ?>" <?= $selected ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Project Details Section -->
            <div>
                <h3 class="text-xs font-bold text-[#c9a84c] uppercase tracking-wider mb-4 pb-1 border-b border-[#1e3e68] flex items-center gap-2">
                    <i class="fa-solid fa-building"></i>
                    <span>Project Information</span>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Project Name *</label>
                        <input type="text" name="project_name" required 
                               value="<?= htmlspecialchars($_POST['project_name'] ?? $assessment['project_name'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Project Address</label>
                        <textarea name="project_address" rows="2"
                                  class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors"><?= htmlspecialchars($_POST['project_address'] ?? $assessment['project_address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Project Type</label>
                        <input type="text" name="project_type" placeholder="e.g. Commercial Interior, Residential"
                               value="<?= htmlspecialchars($_POST['project_type'] ?? $assessment['project_type'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Estimated Budget ($)</label>
                        <input type="number" step="0.01" name="estimated_budget" placeholder="0.00"
                               value="<?= htmlspecialchars($_POST['estimated_budget'] ?? $assessment['estimated_budget'] ?? '') ?>"
                               class="w-full px-3.5 py-2.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-sm text-white focus:outline-none focus:border-[#c9a84c] transition-colors">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-[#1e3e68] flex items-center justify-end gap-3">
                <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>" 
                   class="px-5 py-2.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-300 text-sm font-semibold rounded-lg border border-[#1e3e68] transition-all">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-sm font-bold rounded-lg shadow-lg transition-all flex items-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
