<?php
/**
 * United Five Construction - Assessments Dashboard & Lead Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';

requireLogin();
$currentUser = getCurrentUser();

$pdo = getDbConnection();

// Filtering
$statusFilter = trim($_GET['status'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        a.*, 
        u.name AS assessor_name,
        (SELECT COUNT(*) FROM follow_up_tasks t WHERE t.assessment_id = a.id AND t.status = 'OPEN') AS open_tasks_count
    FROM assessments a
    LEFT JOIN users u ON a.assessor_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (a.client_name LIKE ? OR a.project_address LIKE ? OR a.assessment_number LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY a.updated_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assessments = $stmt->fetchAll();

// Counts for filter pills
$countsStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM assessments GROUP BY status");
$statusCounts = [
    'ALL' => 0,
    'IN_PROGRESS' => 0,
    'HOLD' => 0,
    'ESCALATED' => 0,
    'PROCEED_TO_PROPOSAL' => 0,
    'NOT_A_FIT' => 0
];
while ($row = $countsStmt->fetch()) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
    $statusCounts['ALL'] += (int)$row['cnt'];
}

$pageTitle = 'Assessments — UFC Pre-Assessment System';
require_once __DIR__ . '/../components/header.php';
?>

<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Client Pre-Assessments</h1>
            <p class="text-xs text-slate-400 mt-1">Four-phase qualification gates for private client leads.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/ufc_v1/assessment/start.php" 
               class="px-5 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] font-bold text-xs rounded shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Intake New Lead</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-md">
        <!-- Status Filter Tabs -->
        <div class="flex flex-wrap items-center gap-1 text-xs">
            <a href="/ufc_v1/admin/assessments.php" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= empty($statusFilter) ? 'bg-[#c9a84c] text-[#060f1e]' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                All (<?= $statusCounts['ALL'] ?>)
            </a>
            <a href="/ufc_v1/admin/assessments.php?status=IN_PROGRESS" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= ($statusFilter === 'IN_PROGRESS') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                In Progress (<?= $statusCounts['IN_PROGRESS'] ?>)
            </a>
            <a href="/ufc_v1/admin/assessments.php?status=HOLD" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= ($statusFilter === 'HOLD') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                Hold / Req (<?= $statusCounts['HOLD'] ?>)
            </a>
            <a href="/ufc_v1/admin/assessments.php?status=ESCALATED" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= ($statusFilter === 'ESCALATED') ? 'bg-purple-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                Escalated (<?= $statusCounts['ESCALATED'] ?>)
            </a>
            <a href="/ufc_v1/admin/assessments.php?status=PROCEED_TO_PROPOSAL" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= ($statusFilter === 'PROCEED_TO_PROPOSAL') ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                Passed (<?= $statusCounts['PROCEED_TO_PROPOSAL'] ?>)
            </a>
            <a href="/ufc_v1/admin/assessments.php?status=NOT_A_FIT" 
               class="px-3 py-1.5 rounded font-semibold transition-colors <?= ($statusFilter === 'NOT_A_FIT') ? 'bg-red-700 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                Not A Fit (<?= $statusCounts['NOT_A_FIT'] ?>)
            </a>
        </div>

        <!-- Search Form -->
        <form action="" method="GET" class="w-full md:w-64">
            <?php if (!empty($statusFilter)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php endif; ?>
            <div class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                       placeholder="Search client, address..." 
                       class="w-full pl-9 pr-3 py-1.5 bg-[#060f1e] border border-[#1e3e68] rounded-md text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c]">
                <svg class="w-4 h-4 text-slate-500 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </form>
    </div>

    <!-- Assessments Table -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#1e3e68] bg-[#0a172c]/80 text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 font-semibold">Ref / Client</th>
                        <th class="py-3.5 px-4 font-semibold">Project Location</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Phase</th>
                        <th class="py-3.5 px-4 font-semibold">Status / Gate</th>
                        <th class="py-3.5 px-4 font-semibold">Assessor</th>
                        <th class="py-3.5 px-4 font-semibold">Updated</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e3e68]">
                    <?php if (empty($assessments)): ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                No assessment records found. Click <a href="/ufc_v1/assessment/start.php" class="text-[#c9a84c] underline font-semibold">Intake New Lead</a> to start.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assessments as $ass): 
                            $status = $ass['status'];
                            $badgeClass = 'bg-blue-950/80 text-blue-300 border-blue-600';
                            $statusLabel = 'In Progress';

                            if ($status === 'PROCEED_TO_PROPOSAL') {
                                $badgeClass = 'bg-emerald-950/80 text-emerald-300 border-emerald-500';
                                $statusLabel = 'Passed · Proceed to Proposal';
                            } elseif ($status === 'HOLD') {
                                $badgeClass = 'bg-amber-950/80 text-[#c9a84c] border-amber-500';
                                $statusLabel = 'HOLD — Requirements Pending';
                            } elseif ($status === 'NOT_A_FIT') {
                                $badgeClass = 'bg-red-950/80 text-red-300 border-red-500';
                                $statusLabel = 'Not A Fit';
                            } elseif ($status === 'ESCALATED') {
                                $badgeClass = 'bg-purple-950/80 text-purple-300 border-purple-500';
                                $statusLabel = 'Escalated · CEO Review';
                            }
                        ?>
                        <tr class="hover:bg-[#1a3a5c]/40 transition-colors">
                            <td class="py-3.5 px-4">
                                <div class="font-mono text-[11px] text-slate-400"><?= htmlspecialchars($ass['assessment_number']) ?></div>
                                <a href="/ufc_v1/admin/assessment.php?id=<?= $ass['id'] ?>" class="font-bold text-sm text-slate-100 hover:text-[#c9a84c] transition-colors">
                                    <?= htmlspecialchars($ass['client_name']) ?>
                                </a>
                            </td>
                            <td class="py-3.5 px-4 text-slate-300 max-w-xs truncate">
                                <?= htmlspecialchars($ass['project_address']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-1 rounded bg-[#1a3a5c] text-[#c9a84c] font-bold border border-[#234d7a]">
                                    P<?= $ass['current_phase'] ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded text-[11px] font-semibold border inline-block <?= $badgeClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                                <?php if ($ass['hold_deadline_date'] && $status === 'HOLD'): ?>
                                    <div class="text-[10px] text-slate-400 mt-1">Due: <?= formatDate($ass['hold_deadline_date']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-300">
                                <?= htmlspecialchars($ass['assessor_name'] ?? 'System') ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                                <?= formatDate($ass['updated_at'], 'M j, Y H:i') ?>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2">
                                <a href="/ufc_v1/assessment/question.php?id=<?= $ass['id'] ?>" 
                                   class="px-3 py-1 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 rounded border border-[#1e3e68] text-xs font-semibold transition-colors">
                                    Run
                                </a>
                                <a href="/ufc_v1/admin/assessment.php?id=<?= $ass['id'] ?>" 
                                   class="px-3 py-1 bg-[#060f1e] hover:bg-[#1a3a5c] text-[#c9a84c] rounded border border-[#1e3e68] text-xs font-semibold transition-colors">
                                    Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
