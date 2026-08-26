<?php
/**
 * United Five Construction - Phase Navigation & Gate Status Indicator
 */
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';

if (!isset($assessment) || empty($assessment['id'])) {
    return;
}

$phases = getAllPhases();
$currentPhaseNum = (int)$assessment['current_phase'];
$pdo = getDbConnection();

// Fetch phase results for this assessment
$stmtResults = $pdo->prepare("SELECT pr.*, p.phase_number FROM phase_results pr JOIN phases p ON pr.phase_id = p.id WHERE pr.assessment_id = ?");
$stmtResults->execute([$assessment['id']]);
$resultsRows = $stmtResults->fetchAll();
$phaseResultsMap = [];
foreach ($resultsRows as $r) {
    $phaseResultsMap[$r['phase_number']] = $r;
}
?>

<div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-4 mb-6 shadow-md no-print">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <?php foreach ($phases as $p): 
            $pNum = (int)$p['phase_number'];
            $pRes = $phaseResultsMap[$pNum] ?? null;
            $unlocked = isPhaseUnlocked((int)$assessment['id'], $pNum);
            $isCurrent = ($pNum === $activePhaseNumber);

            $badgeClass = 'bg-slate-800/80 text-slate-400 border-slate-700';
            $icon = '🔒';
            $statusText = 'Locked';

            if ($pRes && $pRes['status'] === 'PASS') {
                $badgeClass = 'bg-emerald-950/60 text-emerald-300 border-emerald-600/50';
                $icon = '✓';
                $statusText = 'Passed';
            } elseif ($pRes && $pRes['status'] === 'FAIL_HOLD') {
                $badgeClass = 'bg-amber-950/60 text-[#c9a84c] border-amber-600/50';
                $icon = '⏳';
                $statusText = 'Hold / Req';
            } elseif ($pRes && $pRes['status'] === 'FAIL_STOP') {
                $badgeClass = 'bg-red-950/60 text-red-300 border-red-600/50';
                $icon = '✕';
                $statusText = 'Not A Fit';
            } elseif ($pRes && $pRes['status'] === 'ESCALATED') {
                $badgeClass = 'bg-purple-950/60 text-purple-300 border-purple-600/50';
                $icon = '⚠';
                $statusText = 'Escalated';
            } elseif ($unlocked) {
                $badgeClass = 'bg-blue-950/60 text-blue-300 border-blue-600/50';
                $icon = '→';
                $statusText = ($isCurrent) ? 'Active' : 'Unlocked';
            }
        ?>
        <div class="p-3 rounded-lg border <?= $isCurrent ? 'ring-2 ring-[#c9a84c] bg-[#1a3a5c]/80' : 'bg-[#0a172c]/60' ?> <?= $badgeClass ?> flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold tracking-wider uppercase">Phase <?= $pNum ?></span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border border-current"><?= $icon ?> <?= $statusText ?></span>
            </div>
            <div class="mt-2 text-xs font-medium text-slate-200 line-clamp-1">
                <?= htmlspecialchars($p['title']) ?>
            </div>
            <div class="text-[10px] text-slate-400 mt-1 italic line-clamp-1">
                "<?= htmlspecialchars($p['the_question']) ?>"
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
