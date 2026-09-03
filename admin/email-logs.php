<?php
/**
 * United Five Construction - Email Communication & Audit Logs
 * Insights, deliverability statistics, and complete historical audit trail of all emails.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/DateService.php';

requireLogin();
$currentUser = getCurrentUser();
$pdo = getDbConnection();
ensureCheckReportColumns($pdo);

// Filtering & Search Inputs
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter   = trim($_GET['type'] ?? '');
$searchQuery  = trim($_GET['search'] ?? '');
$filterAssId  = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;

// Base query for email log metrics
$totalEmails = (int)$pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
$sentCount   = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'SENT'")->fetchColumn();
$failedCount = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'FAILED'")->fetchColumn();
$successRate = $totalEmails > 0 ? round(($sentCount / $totalEmails) * 100, 1) : 100;

// Type breakdown counts
$slaCount    = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'SLA_REMINDER'")->fetchColumn();
$letterCount = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'REQUIREMENTS_LETTER'")->fetchColumn();
$leadCount   = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'LEAD_SUMMARY'")->fetchColumn();
$customCount = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE email_type = 'CUSTOM'")->fetchColumn();

// Build filtered list query
$sql = "
    SELECT 
        l.*,
        a.assessment_number,
        a.client_name,
        a.project_name
    FROM email_logs l
    LEFT JOIN assessments a ON l.assessment_id = a.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['SENT', 'FAILED'])) {
    $sql .= " AND l.status = ?";
    $params[] = $statusFilter;
}

if (!empty($typeFilter) && in_array($typeFilter, ['REQUIREMENTS_LETTER', 'LEAD_SUMMARY', 'SLA_REMINDER', 'CUSTOM'])) {
    $sql .= " AND l.email_type = ?";
    $params[] = $typeFilter;
}

if ($filterAssId > 0) {
    $sql .= " AND l.assessment_id = ?";
    $params[] = $filterAssId;
}

if (!empty($searchQuery)) {
    $sql .= " AND (l.recipient_email LIKE ? OR l.subject LIKE ? OR a.client_name LIKE ? OR a.assessment_number LIKE ? OR a.project_name LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY l.sent_at DESC LIMIT 150";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Email Logs & Insights — UFC Framework';
require_once __DIR__ . '/../components/header.php';
?>

<div class="space-y-6">
    <!-- Top Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-[#1a3a5c] text-[#c9a84c] flex items-center justify-center border border-[#234d7a] shadow-sm">
                    <i class="fa-solid fa-envelope-open-text text-sm"></i>
                </span>
                <h1 class="font-serif text-2xl font-bold text-white tracking-tight">Email Communication Logs</h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Real-time delivery statistics, automated SLA notifications, and complete outbound email audit trail.
            </p>
        </div>

        <?php if ($filterAssId > 0): ?>
            <div>
                <a href="/ufc_v1/admin/email-logs.php" 
                   class="px-3.5 py-1.5 rounded-lg bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold border border-[#1e3e68] transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-xmark text-xs"></i>
                    <span>Clear Assessment Filter</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══ INSIGHT KPI CARDS ═════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Dispatched -->
        <div class="p-5 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-md flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Outbound</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-white mt-1"><?= number_format($totalEmails) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Recorded communications</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-[#102847] border border-[#1e3e68] flex items-center justify-center text-[#c9a84c] text-xl">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
        </div>

        <!-- Success Rate -->
        <div class="p-5 rounded-xl bg-[#0d1f3c] border border-emerald-600/50 shadow-md flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-400">Delivery Success</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-emerald-400 mt-1"><?= $successRate ?>%</div>
                <div class="text-[11px] text-slate-400 mt-0.5"><?= number_format($sentCount) ?> delivered successfully</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-950/80 border border-emerald-600/50 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <!-- Automated SLA Reminders -->
        <div class="p-5 rounded-xl bg-[#0d1f3c] border border-amber-600/50 shadow-md flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-[#c9a84c]">SLA Reminders</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold text-[#c9a84c] mt-1"><?= number_format($slaCount) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Auto-sent every 3 days</div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-950/80 border border-amber-600/50 flex items-center justify-center text-[#c9a84c] text-xl">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>

        <!-- Delivery Failures -->
        <div class="p-5 rounded-xl bg-[#0d1f3c] border <?= $failedCount > 0 ? 'border-red-600/60' : 'border-[#1e3e68]' ?> shadow-md flex items-center justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider <?= $failedCount > 0 ? 'text-red-400' : 'text-slate-400' ?>">Delivery Issues</div>
                <div class="text-2xl sm:text-3xl font-serif font-bold <?= $failedCount > 0 ? 'text-red-400' : 'text-white' ?> mt-1"><?= number_format($failedCount) ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Failed / rejected dispatches</div>
            </div>
            <div class="w-12 h-12 rounded-xl <?= $failedCount > 0 ? 'bg-red-950/80 border border-red-600/60 text-red-400' : 'bg-[#102847] border border-[#1e3e68] text-slate-400' ?> flex items-center justify-center text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>

    <!-- ══ FILTER & SEARCH BAR ═══════════════════════════════════════════════ -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-4 shadow-md space-y-3">
        <form method="GET" action="/ufc_v1/admin/email-logs.php" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <?php if ($filterAssId > 0): ?>
                <input type="hidden" name="assessment_id" value="<?= $filterAssId ?>">
            <?php endif; ?>

            <!-- Status Tabs & Filter Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-400 font-semibold mr-1">Status:</span>
                <a href="?status=&type=<?= urlencode($typeFilter) ?>&search=<?= urlencode($searchQuery) ?><?= $filterAssId ? "&assessment_id={$filterAssId}" : '' ?>" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= empty($statusFilter) ? 'bg-[#c9a84c] text-[#060f1e]' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                    All Statuses
                </a>
                <a href="?status=SENT&type=<?= urlencode($typeFilter) ?>&search=<?= urlencode($searchQuery) ?><?= $filterAssId ? "&assessment_id={$filterAssId}" : '' ?>" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $statusFilter === 'SENT' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                    Delivered (<?= $sentCount ?>)
                </a>
                <a href="?status=FAILED&type=<?= urlencode($typeFilter) ?>&search=<?= urlencode($searchQuery) ?><?= $filterAssId ? "&assessment_id={$filterAssId}" : '' ?>" 
                   class="px-3 py-1.5 rounded-lg font-semibold transition-colors <?= $statusFilter === 'FAILED' ? 'bg-red-600 text-white' : 'text-slate-300 hover:bg-[#1a3a5c]' ?>">
                    Failed (<?= $failedCount ?>)
                </a>
            </div>

            <!-- Type Filter Dropdown & Search Field -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <!-- Type Filter -->
                <select name="type" onchange="this.form.submit()" 
                        class="px-3 py-1.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-slate-200 focus:outline-none focus:border-[#c9a84c] transition-colors">
                    <option value="" <?= empty($typeFilter) ? 'selected' : '' ?>>All Communication Types</option>
                    <option value="SLA_REMINDER" <?= $typeFilter === 'SLA_REMINDER' ? 'selected' : '' ?>>SLA Reminders (<?= $slaCount ?>)</option>
                    <option value="REQUIREMENTS_LETTER" <?= $typeFilter === 'REQUIREMENTS_LETTER' ? 'selected' : '' ?>>Requirements Letters (<?= $letterCount ?>)</option>
                    <option value="LEAD_SUMMARY" <?= $typeFilter === 'LEAD_SUMMARY' ? 'selected' : '' ?>>Lead Summaries (<?= $leadCount ?>)</option>
                    <option value="CUSTOM" <?= $typeFilter === 'CUSTOM' ? 'selected' : '' ?>>Custom Messages (<?= $customCount ?>)</option>
                </select>

                <!-- Search Field -->
                <div class="relative min-w-[220px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                           placeholder="Search recipient, subject..."
                           class="w-full pl-8 pr-3 py-1.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#c9a84c] transition-colors">
                </div>

                <button type="submit" 
                        class="px-3.5 py-1.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-xs font-semibold rounded-lg border border-[#1e3e68] transition-colors cursor-pointer">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- ══ EMAIL LOGS TABLE ═════════════════════════════════════════════════ -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[#1e3e68] bg-[#0a172c]/80 text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 font-semibold">Date &amp; Time</th>
                        <th class="py-3.5 px-4 font-semibold">Type</th>
                        <th class="py-3.5 px-4 font-semibold">Recipient</th>
                        <th class="py-3.5 px-4 font-semibold">Subject</th>
                        <th class="py-3.5 px-4 font-semibold">Assessment Ref</th>
                        <th class="py-3.5 px-4 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e3e68]">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <i class="fa-regular fa-envelope text-3xl text-slate-500"></i>
                                    <span class="text-sm font-semibold">No email logs found matching criteria.</span>
                                    <span class="text-xs text-slate-500">Outbound emails sent via letter engine, lead sheets, or automated cron will appear here.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $typeKey = $log['email_type'] ?? 'GENERAL';
                            $typeBadgeClass = match($typeKey) {
                                'SLA_REMINDER'        => 'bg-amber-950/80 text-[#c9a84c] border-amber-600/50',
                                'REQUIREMENTS_LETTER' => 'bg-blue-950/80 text-blue-300 border-blue-600/50',
                                'LEAD_SUMMARY'        => 'bg-sky-950/80 text-sky-300 border-sky-600/50',
                                'CUSTOM'              => 'bg-purple-950/80 text-purple-300 border-purple-600/50',
                                default               => 'bg-slate-800 text-slate-300 border-slate-700',
                            };
                            $typeLabel = match($typeKey) {
                                'SLA_REMINDER'        => 'SLA Reminder',
                                'REQUIREMENTS_LETTER' => 'Notice Letter',
                                'LEAD_SUMMARY'        => 'Lead Summary',
                                'CUSTOM'              => 'Custom Email',
                                default               => 'General Email',
                            };
                            $isSent = ($log['status'] === 'SENT');
                        ?>
                        <tr class="hover:bg-[#1a3a5c]/30 transition-colors">
                            <!-- Date & Time -->
                            <td class="py-3.5 px-4 font-mono text-slate-300 whitespace-nowrap">
                                <div><?= formatDate($log['sent_at'], 'M j, Y') ?></div>
                                <div class="text-[10px] text-slate-500 font-mono"><?= formatDate($log['sent_at'], 'H:i:s') ?></div>
                            </td>

                            <!-- Type Badge -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold border <?= $typeBadgeClass ?> inline-flex items-center gap-1.5">
                                    <?php if ($typeKey === 'SLA_REMINDER'): ?>
                                        <i class="fa-solid fa-clock text-[10px]"></i>
                                    <?php elseif ($typeKey === 'REQUIREMENTS_LETTER'): ?>
                                        <i class="fa-regular fa-file-lines text-[10px]"></i>
                                    <?php elseif ($typeKey === 'LEAD_SUMMARY'): ?>
                                        <i class="fa-solid fa-address-card text-[10px]"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-envelope text-[10px]"></i>
                                    <?php endif; ?>
                                    <span><?= $typeLabel ?></span>
                                </span>
                            </td>

                            <!-- Recipient -->
                            <td class="py-3.5 px-4 font-semibold text-slate-200">
                                <div><?= htmlspecialchars($log['recipient_email']) ?></div>
                            </td>

                            <!-- Subject -->
                            <td class="py-3.5 px-4 text-slate-300 max-w-sm truncate" title="<?= htmlspecialchars($log['subject']) ?>">
                                <div class="truncate font-medium text-white"><?= htmlspecialchars($log['subject']) ?></div>
                                <?php if (!empty($log['error_message'])): ?>
                                    <div class="text-[10px] text-red-400 mt-0.5 truncate" title="<?= htmlspecialchars($log['error_message']) ?>">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i><?= htmlspecialchars($log['error_message']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Assessment Link -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <?php if (!empty($log['assessment_id'])): ?>
                                    <a href="/ufc_v1/admin/assessment.php?id=<?= (int)$log['assessment_id'] ?>" 
                                       class="font-mono text-xs font-bold text-[#c9a84c] hover:underline flex items-center gap-1.5">
                                        <span><?= htmlspecialchars($log['assessment_number'] ?? ('#' . $log['assessment_id'])) ?></span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    </a>
                                    <?php if (!empty($log['client_name'])): ?>
                                        <div class="text-[10px] text-slate-400 truncate max-w-[130px]"><?= htmlspecialchars($log['client_name']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-500">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <?php if ($isSent): ?>
                                    <span class="px-2.5 py-1 rounded text-[11px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500 shadow-sm inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <span>Sent</span>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded text-[11px] font-bold bg-red-950/80 text-red-300 border border-red-600/60 shadow-sm inline-flex items-center gap-1"
                                          title="<?= htmlspecialchars($log['error_message'] ?? 'Delivery failed') ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                        <span>Failed</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="p-3 bg-[#0a172c]/70 border-t border-[#1e3e68] flex items-center justify-between text-xs text-slate-400">
                <span>Showing <?= count($logs) ?> most recent logs</span>
                <span>Audit trail stored in <code>email_logs</code> table</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
