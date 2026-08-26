<?php
/**
 * United Five Construction - Decline Log (Quarterly Intake Quality Review)
 * PDF Specification Section 4 Page 16
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo = getDbConnection();

$stmt = $pdo->query("
    SELECT 
        a.id,
        a.assessment_number,
        a.client_name,
        a.project_address,
        a.current_phase,
        a.decline_reason,
        a.decline_notes,
        a.completed_at,
        a.updated_at,
        u.name as assessor_name
    FROM assessments a
    LEFT JOIN users u ON a.assessor_id = u.id
    WHERE a.status = 'NOT_A_FIT'
    ORDER BY a.updated_at DESC
");
$declines = $stmt->fetchAll();

$pageTitle = 'Decline Log — UFC Pre-Assessment';
require_once __DIR__ . '/../components/header.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Quarterly Decline Log</h1>
        <p class="text-xs text-slate-400 mt-1">
            One line per NOT A FIT decision. Review quarterly to identify upstream intake filtering opportunities.
        </p>
    </div>

    <!-- Decline Log Table -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#1e3e68] bg-[#0a172c]/80 text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 font-semibold">Date</th>
                        <th class="py-3.5 px-4 font-semibold">Client</th>
                        <th class="py-3.5 px-4 font-semibold">Property Address</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Stopped Phase</th>
                        <th class="py-3.5 px-4 font-semibold">Primary Reason</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Letter</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e3e68]">
                    <?php if (empty($declines)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                No declined assessments recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($declines as $d): ?>
                        <tr class="hover:bg-[#1a3a5c]/40 transition-colors">
                            <td class="py-3.5 px-4 font-mono text-slate-300">
                                <?= formatDate($d['completed_at'] ?: $d['updated_at']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-100">
                                <?= htmlspecialchars($d['client_name']) ?>
                                <div class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($d['assessment_number']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-300 max-w-xs truncate">
                                <?= htmlspecialchars($d['project_address']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded bg-red-950 text-red-300 font-bold border border-red-800">
                                    Phase <?= $d['current_phase'] ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-semibold text-red-400">
                                    <?= htmlspecialchars(str_replace('_', ' ', $d['decline_reason'] ?? 'STOP TRIGGER')) ?>
                                </span>
                                <?php if (!empty($d['decline_notes'])): ?>
                                    <div class="text-[11px] text-slate-400 italic mt-0.5"><?= htmlspecialchars($d['decline_notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <a href="/ufc_v1/assessment/decline-letter.php?id=<?= $d['id'] ?>" target="_blank"
                                   class="text-xs text-red-400 hover:text-red-300 font-semibold underline">
                                    Decline Letter
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
