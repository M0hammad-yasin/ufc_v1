<?php
/**
 * United Five Construction - Follow-Up Tasks & Deficiency Tracker
 * Grouped by Assessment with accordion-style expand / collapse.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';

requireLogin();
$currentUser = getCurrentUser();

$pdo = getDbConnection();

// ── Mark task resolved ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_task_id'])) {
    $taskId = (int)$_POST['resolve_task_id'];
    $stmtR  = $pdo->prepare("UPDATE follow_up_tasks SET status = 'RESOLVED', resolved_at = NOW() WHERE id = ?");
    $stmtR->execute([$taskId]);
    setFlashMessage('success', 'Task marked as resolved.');
    $redirectQs = trim($_POST['_redirect_qs'] ?? '');
    $redirectUrl = '/ufc_v1/admin/tasks.php' . ($redirectQs !== '' ? '?' . $redirectQs : '');
    header("Location: $redirectUrl");
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus     = $_GET['status'] ?? 'OPEN';
$expandAssessment = isset($_GET['open']) ? (int)$_GET['open'] : null; // auto-open a specific assessment

// ── Fetch all tasks with assessment meta ──────────────────────────────────────
$sql = "
    SELECT
        t.*,
        a.client_name,
        a.project_name,
        a.project_address,
        a.assessment_number,
        a.status AS assessment_status,
        q.question_number,
        q.question_text,
        eb.reason
    FROM follow_up_tasks t
    JOIN assessments a ON t.assessment_id = a.id
    JOIN questions   q ON t.question_id   = q.id
    JOIN explain_blocks eb ON t.explain_block_id = eb.id
    WHERE 1=1
";
$params = [];
if (!empty($filterStatus) && $filterStatus !== 'ALL') {
    $sql    .= " AND t.status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY t.target_cure_date ASC, t.created_at DESC";

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$allTasks = $stmt->fetchAll();

// ── Group tasks by assessment ─────────────────────────────────────────────────
$grouped = []; // [ assessmentId => [ 'meta' => [...], 'tasks' => [...] ] ]
foreach ($allTasks as $task) {
    $aid = $task['assessment_id'];
    if (!isset($grouped[$aid])) {
        $grouped[$aid] = [
            'meta' => [
                'id'                => $aid,
                'assessment_number' => $task['assessment_number'],
                'client_name'       => $task['client_name'],
                'project_name'      => $task['project_name'],
                'assessment_status' => $task['assessment_status'],
            ],
            'tasks' => [],
        ];
    }
    $grouped[$aid]['tasks'][] = $task;
}

// ── Summary counts ────────────────────────────────────────────────────────────
$totalStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM follow_up_tasks GROUP BY status");
$totals = ['OPEN' => 0, 'RESOLVED' => 0, 'ALL' => 0];
while ($row = $totalStmt->fetch()) {
    $totals[$row['status']] = (int)$row['cnt'];
    $totals['ALL'] += (int)$row['cnt'];
}

$pageTitle = 'Follow-Up Tasks — UFC Pre-Assessment';
require_once __DIR__ . '/../components/header.php';
?>

<style>
    .task-accordion { transition: all 0.22s cubic-bezier(0.4,0,0.2,1); }
    .assessment-card { transition: box-shadow 0.2s, border-color 0.2s; }
    .assessment-card:hover { box-shadow: 0 0 0 1px #c9a84c44, 0 4px 32px #00000060; }
    .assessment-card.open { border-color: #c9a84c55; }
    .task-rows-wrap { display: none; }
    .task-rows-wrap.expanded { display: block; }
    .chevron-icon { transition: transform 0.22s ease; }
    .chevron-icon.rotated { transform: rotate(180deg); }
    .status-badge-assessment {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; letter-spacing: .04em;
    }
</style>

<div class="space-y-6">

    <!-- ── Page Header ── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Deficiency Follow-Up Tasks</h1>
            <p class="text-xs text-slate-400 mt-1">Grouped by assessment — click a row to expand its tasks.</p>
        </div>

        <!-- Summary pills -->
        <div class="flex items-center gap-2 text-xs flex-wrap">
            <a href="/ufc_v1/admin/tasks.php?status=OPEN"
               class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'OPEN') ? 'bg-[#c9a84c] text-[#060f1e]' : 'bg-[#1a3a5c] text-slate-300 hover:bg-[#234d7a]' ?>">
                Open&nbsp;<span class="opacity-75">(<?= $totals['OPEN'] ?>)</span>
            </a>
            <a href="/ufc_v1/admin/tasks.php?status=RESOLVED"
               class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'RESOLVED') ? 'bg-emerald-700 text-white' : 'bg-[#1a3a5c] text-slate-300 hover:bg-[#234d7a]' ?>">
                Resolved&nbsp;<span class="opacity-75">(<?= $totals['RESOLVED'] ?>)</span>
            </a>
            <a href="/ufc_v1/admin/tasks.php?status=ALL"
               class="px-3 py-1.5 rounded font-semibold <?= ($filterStatus === 'ALL') ? 'bg-slate-600 text-white' : 'bg-[#1a3a5c] text-slate-300 hover:bg-[#234d7a]' ?>">
                All&nbsp;<span class="opacity-75">(<?= $totals['ALL'] ?>)</span>
            </a>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <!-- ── Empty State ── -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-14 text-center">
        <i class="fa-solid fa-circle-check text-3xl text-emerald-500 mb-3"></i>
        <p class="text-slate-300 font-semibold text-sm">No follow-up tasks for this filter.</p>
        <p class="text-slate-500 text-xs mt-1">All deficiencies are either resolved or none have been logged yet.</p>
    </div>
    <?php else: ?>

    <!-- ── Assessment Cards ── -->
    <div class="space-y-3" id="tasks-accordion">
        <?php foreach ($grouped as $assessmentId => $group):
            $meta  = $group['meta'];
            $tasks = $group['tasks'];

            $openCount     = count(array_filter($tasks, fn($t) => $t['status'] === 'OPEN'));
            $resolvedCount = count($tasks) - $openCount;
            $isAutoOpen    = ($expandAssessment === $assessmentId);

            // Assessment status badge
            $asBadge = match($meta['assessment_status']) {
                'PROCEED_TO_PROPOSAL' => ['bg-emerald-950/80 text-emerald-300 border-emerald-600', 'Passed'],
                'HOLD'                => ['bg-amber-950/80 text-[#c9a84c] border-amber-600', 'HOLD'],
                'NOT_A_FIT'           => ['bg-red-950/80 text-red-300 border-red-600', 'Not A Fit'],
                'ESCALATED'           => ['bg-purple-950/80 text-purple-300 border-purple-600', 'Escalated'],
                default               => ['bg-blue-950/80 text-blue-300 border-blue-600', 'In Progress'],
            };
        ?>
        <div class="assessment-card bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-lg overflow-hidden <?= $isAutoOpen ? 'open' : '' ?>"
             id="acard-<?= $assessmentId ?>">

            <!-- ── Assessment Header (clickable) ── -->
            <button type="button"
                    onclick="toggleAssessment(<?= $assessmentId ?>)"
                    class="w-full text-left px-5 py-4 flex items-center gap-4 hover:bg-[#1a3a5c]/30 transition-colors focus:outline-none"
                    id="atoggle-<?= $assessmentId ?>"
                    aria-expanded="<?= $isAutoOpen ? 'true' : 'false' ?>">

                <!-- Chevron -->
                <span class="chevron-icon <?= $isAutoOpen ? 'rotated' : '' ?> text-slate-400 flex-shrink-0" id="achevron-<?= $assessmentId ?>">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </span>

                <!-- Assessment Number + Client -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-0.5">
                        <span class="font-mono text-[11px] text-slate-400"><?= htmlspecialchars($meta['assessment_number']) ?></span>
                        <span class="status-badge-assessment border <?= $asBadge[0] ?>"><?= $asBadge[1] ?></span>
                    </div>
                    <div class="font-bold text-sm text-white truncate"><?= htmlspecialchars($meta['client_name']) ?></div>
                    <?php if (!empty($meta['project_name'])): ?>
                    <div class="text-[11px] text-slate-400 truncate mt-0.5"><?= htmlspecialchars($meta['project_name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Task Count Badges -->
                <div class="flex-shrink-0 flex items-center gap-2 ml-auto">
                    <?php if ($openCount > 0): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-300 font-bold text-[11px]">
                        <i class="fa-solid fa-circle-exclamation text-[10px]"></i>
                        <?= $openCount ?> Open
                    </span>
                    <?php endif; ?>
                    <?php if ($resolvedCount > 0): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold text-[11px]">
                        <i class="fa-solid fa-circle-check text-[10px]"></i>
                        <?= $resolvedCount ?> Done
                    </span>
                    <?php endif; ?>

                    <!-- Link to assessment detail -->
                    <a href="/ufc_v1/admin/assessment.php?id=<?= $assessmentId ?>"
                       onclick="event.stopPropagation()"
                       title="Open Assessment"
                       class="w-7 h-7 rounded bg-[#1a3a5c] hover:bg-[#234d7a] border border-[#1e3e68] flex items-center justify-center text-slate-400 hover:text-white transition-colors">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    </a>
                </div>
            </button>

            <!-- ── Tasks Table (expandable) ── -->
            <div class="task-rows-wrap <?= $isAutoOpen ? 'expanded' : '' ?>"
                 id="atasks-<?= $assessmentId ?>">
                <div class="border-t border-[#1e3e68]">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#0a172c]/80 text-slate-400 uppercase tracking-wider border-b border-[#1e3e68]">
                                <th class="py-2.5 px-5 font-semibold">Target Cure Date</th>
                                <th class="py-2.5 px-4 font-semibold">Responsible Party</th>
                                <th class="py-2.5 px-4 font-semibold">Deficiency / Item</th>
                                <th class="py-2.5 px-4 font-semibold">Status</th>
                                <th class="py-2.5 px-4 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#1e3e68]/60">
                            <?php foreach ($tasks as $task):
                                $isOverdue = ($task['status'] === 'OPEN' && strtotime($task['target_cure_date']) < time());
                            ?>
                            <tr class="hover:bg-[#1a3a5c]/30 transition-colors <?= $isOverdue ? 'bg-red-950/10' : '' ?>">
                                <!-- Date -->
                                <td class="py-3 px-5 font-mono font-bold <?= $isOverdue ? 'text-red-400' : 'text-[#c9a84c]' ?> whitespace-nowrap">
                                    <?= formatDate($task['target_cure_date']) ?>
                                    <?php if ($isOverdue): ?>
                                    <span class="block text-[10px] text-red-400 uppercase font-semibold">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Responsible -->
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-[#1a3a5c] border border-[#234d7a] text-[11px] font-bold text-slate-200 whitespace-nowrap">
                                        <?= htmlspecialchars($task['responsible_party']) ?>
                                    </span>
                                </td>
                                <!-- Question / Reason -->
                                <td class="py-3 px-4 max-w-xs">
                                    <div class="font-bold text-slate-100">
                                        <span class="text-[#c9a84c] mr-1 font-mono">Q<?= $task['question_number'] ?></span>
                                        <?= htmlspecialchars(mb_substr($task['question_text'], 0, 72)) ?>…
                                    </div>
                                    <div class="text-slate-400 text-[11px] mt-0.5 italic line-clamp-1">
                                        "<?= htmlspecialchars($task['reason']) ?>"
                                    </div>
                                </td>
                                <!-- Status -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($task['status'] === 'OPEN'): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold badge-amber">OPEN</span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold badge-green">RESOLVED</span>
                                    <div class="text-[10px] text-slate-500 mt-0.5"><?= formatDate($task['resolved_at'], 'M j, Y') ?></div>
                                    <?php endif; ?>
                                </td>
                                <!-- Action -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <?php if ($task['status'] === 'OPEN'): ?>
                                    <form action="" method="POST" class="inline">
                                        <?php
                                        // Preserve current URL query params so we return to the same filter+open state
                                        $qs = http_build_query(array_merge($_GET, ['open' => $assessmentId]));
                                        ?>
                                        <input type="hidden" name="resolve_task_id" value="<?= $task['id'] ?>">
                                        <input type="hidden" name="_redirect_qs" value="<?= htmlspecialchars($qs) ?>">
                                        <button type="submit"
                                                onclick="if(!confirm('Mark this task as resolved?')) return false;"
                                                class="px-3 py-1 bg-emerald-800 hover:bg-emerald-700 text-white rounded text-[11px] font-bold transition-colors">
                                            Mark Resolved
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-slate-500 text-[11px]">Closed <?= formatDate($task['resolved_at'], 'M j') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /task-rows-wrap -->
        </div><!-- /assessment-card -->
        <?php endforeach; ?>
    </div><!-- /tasks-accordion -->

    <?php endif; ?>
</div>

<script>
function toggleAssessment(id) {
    const card    = document.getElementById('acard-'    + id);
    const wrap    = document.getElementById('atasks-'   + id);
    const chevron = document.getElementById('achevron-' + id);
    const btn     = document.getElementById('atoggle-'  + id);

    const isOpen  = wrap.classList.contains('expanded');

    // Close all others first
    document.querySelectorAll('.task-rows-wrap.expanded').forEach(el => {
        if (el !== wrap) {
            el.classList.remove('expanded');
            const otherId   = el.id.replace('atasks-', '');
            const otherCard = document.getElementById('acard-'    + otherId);
            const otherChev = document.getElementById('achevron-' + otherId);
            const otherBtn  = document.getElementById('atoggle-'  + otherId);
            if (otherCard) otherCard.classList.remove('open');
            if (otherChev) otherChev.classList.remove('rotated');
            if (otherBtn)  otherBtn.setAttribute('aria-expanded', 'false');
        }
    });

    // Toggle clicked
    if (isOpen) {
        wrap.classList.remove('expanded');
        card.classList.remove('open');
        chevron.classList.remove('rotated');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        wrap.classList.add('expanded');
        card.classList.add('open');
        chevron.classList.add('rotated');
        btn.setAttribute('aria-expanded', 'true');
        // Scroll into view smoothly
        setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 80);
    }
}

// Handle redirect_qs from resolve POST — keep accordion open
(function() {
    const url = new URL(location.href);
    // If we were redirected back with an open= param, it's already set by PHP
})();
</script>

<?php
// Override redirect to preserve ?open= param from resolve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_redirect_qs'])) {
    // This is handled above before the HTML, but keep footer clean
}
require_once __DIR__ . '/../components/footer.php';
?>
