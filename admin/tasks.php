<?php
/**
 * United Five Construction - Follow-Up Tasks & Deficiency Tracker
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';

requireLogin();
$currentUser = getCurrentUser();

$pdo = getDbConnection();

// Mark task resolved if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_task_id'])) {
    $taskId = (int)$_POST['resolve_task_id'];
    $stmtResolve = $pdo->prepare("UPDATE follow_up_tasks SET status = 'RESOLVED', resolved_at = NOW() WHERE id = ?");
    $stmtResolve->execute([$taskId]);
    setFlashMessage('success', 'Task marked as resolved.');
    header("Location: /ufc_v1/admin/tasks.php");
    exit;
}

// Fetch tasks
$filterStatus = $_GET['status'] ?? 'OPEN';
$sql = "
    SELECT 
        t.*, 
        a.client_name, 
        a.project_address, 
        a.assessment_number,
        q.question_number,
        q.question_text,
        eb.reason
    FROM follow_up_tasks t
    JOIN assessments a ON t.assessment_id = a.id
    JOIN questions q ON t.question_id = q.id
    JOIN explain_blocks eb ON t.explain_block_id = eb.id
    WHERE 1=1
";
$params = [];
if (!empty($filterStatus) && $filterStatus !== 'ALL') {
    $sql .= " AND t.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY t.target_cure_date ASC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'Follow-Up Tasks — UFC Pre-Assessment';
require_once __DIR__ . '/../components/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Deficiency Follow-Up Tasks</h1>
            <p class="text-xs text-slate-400 mt-1">Track open Explain Blocks, responsible parties, and target cure dates.</p>
        </div>
        
        <!-- Filter Tabs -->
        <div class="flex items-center gap-2 text-xs">
            <a href="/ufc_v1/admin/tasks.php?status=OPEN" class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'OPEN') ? 'bg-[#c9a84c] text-[#060f1e]' : 'bg-[#1a3a5c] text-slate-300' ?>">
                Open Tasks
            </a>
            <a href="/ufc_v1/admin/tasks.php?status=RESOLVED" class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'RESOLVED') ? 'bg-emerald-700 text-white' : 'bg-[#1a3a5c] text-slate-300' ?>">
                Resolved
            </a>
            <a href="/ufc_v1/admin/tasks.php?status=ALL" class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'ALL') ? 'bg-slate-700 text-white' : 'bg-[#1a3a5c] text-slate-300' ?>">
                All
            </a>
        </div>
    </div>

    <!-- Task List Table -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#1e3e68] bg-[#0a172c]/80 text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 font-semibold">Target Cure Date</th>
                        <th class="py-3.5 px-4 font-semibold">Responsible Party</th>
                        <th class="py-3.5 px-4 font-semibold">Deficiency / Item</th>
                        <th class="py-3.5 px-4 font-semibold">Client & Location</th>
                        <th class="py-3.5 px-4 font-semibold">Status</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e3e68]">
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                No follow-up tasks found for this status filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): 
                            $isOverdue = ($task['status'] === 'OPEN' && strtotime($task['target_cure_date']) < time());
                        ?>
                        <tr class="hover:bg-[#1a3a5c]/40 transition-colors <?= $isOverdue ? 'bg-red-950/20' : '' ?>">
                            <td class="py-3.5 px-4 font-mono font-bold <?= $isOverdue ? 'text-red-400' : 'text-[#c9a84c]' ?>">
                                <?= formatDate($task['target_cure_date']) ?>
                                <?php if ($isOverdue): ?>
                                    <span class="block text-[10px] text-red-400 uppercase font-semibold">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-200">
                                <span class="px-2 py-0.5 rounded bg-[#1a3a5c] border border-[#234d7a] text-[11px]">
                                    <?= htmlspecialchars($task['responsible_party']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 max-w-sm">
                                <div class="font-bold text-slate-100">
                                    <span class="text-[#c9a84c] mr-1 font-mono">Q<?= $task['question_number'] ?></span>
                                    <?= htmlspecialchars(substr($task['question_text'], 0, 70)) ?>...
                                </div>
                                <div class="text-slate-400 text-[11px] mt-1 line-clamp-1 italic">
                                    "<?= htmlspecialchars($task['reason']) ?>"
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="/ufc_v1/admin/assessment.php?id=<?= $task['assessment_id'] ?>" class="font-bold text-slate-200 hover:text-[#c9a84c]">
                                    <?= htmlspecialchars($task['client_name']) ?>
                                </a>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars($task['assessment_number']) ?></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= ($task['status'] === 'OPEN') ? 'badge-amber' : 'badge-green' ?>">
                                    <?= $task['status'] ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <?php if ($task['status'] === 'OPEN'): ?>
                                    <form action="" method="POST" class="inline">
                                        <input type="hidden" name="resolve_task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" class="px-3 py-1 bg-emerald-800 hover:bg-emerald-700 text-white rounded text-[11px] font-bold transition-colors">
                                            Mark Resolved
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[11px]">Closed <?= formatDate($task['resolved_at'], 'M j') ?></span>
                                <?php endif; ?>
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
