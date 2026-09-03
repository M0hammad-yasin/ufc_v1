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
ensureCheckReportColumns($pdo);

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

/**
 * Render table rows for assessments list (used for both full page load and AJAX live search)
 */
function renderAssessmentRows(array $assessments, string $searchQuery = ''): string
{
    ob_start();
    if (empty($assessments)): ?>
        <tr>
            <td colspan="7" class="py-10 text-center text-slate-400">
                <?php if (!empty($searchQuery)): ?>
                    No assessments found matching "<span class="text-slate-200 font-semibold"><?= htmlspecialchars($searchQuery) ?></span>".
                <?php else: ?>
                    No assessment records found. Click <a href="/ufc_v1/assessment/start.php" class="text-[#c9a84c] underline font-semibold">Intake New Lead</a> to start.
                <?php endif; ?>
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

            $sla = getAssessmentSlaStatus($ass);
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
                    <?php if ($sla['is_active']): ?>
                        <div class="mt-1.5 flex items-center">
                            <?= $sla['badge_html'] ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="py-3.5 px-4 text-slate-300">
                    <?= htmlspecialchars($ass['assessor_name'] ?? 'System') ?>
                </td>
                <td class="py-3.5 px-4 text-slate-400 text-[11px]">
                    <?= formatDate($ass['updated_at'], 'M j, Y H:i') ?>
                </td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="relative inline-block text-left row-action-dropdown">
                        <button type="button"
                            onclick="toggleRowDropdown(event, <?= (int)$ass['id'] ?>)"
                            title="Assessment Actions"
                            class="w-8 h-8 rounded-lg bg-[#060f1e] hover:bg-[#1a3a5c] text-slate-300 hover:text-white border border-[#1e3e68] flex items-center justify-center transition-colors cursor-pointer ml-auto">
                            <i class="fa-solid fa-ellipsis-vertical text-sm"></i>
                        </button>

                        <!-- Mini Dropdown Menu -->
                        <div id="row-dropdown-<?= (int)$ass['id'] ?>"
                            class="hidden absolute right-0 mt-1 w-52 rounded-xl bg-[#0d1f3c] border border-[#1e3e68] shadow-2xl z-50 text-left overflow-hidden py-1">
                            <div class="px-3.5 py-1.5 border-b border-[#1e3e68] text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                Quick Actions
                            </div>

                            <!-- Edit Assessment / Questions -->
                            <a href="/ufc_v1/assessment/question.php?id=<?= (int)$ass['id'] ?>"
                                class="px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                                <i class="fa-regular fa-pen-to-square text-blue-400 text-sm"></i>
                                <span>Edit Assessment</span>
                            </a>

                            <!-- Full Details Inspector -->
                            <a href="/ufc_v1/admin/assessment.php?id=<?= (int)$ass['id'] ?>"
                                class="px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                                <i class="fa-solid fa-circle-info text-sky-400 text-sm"></i>
                                <span>Full Details</span>
                            </a>

                            <!-- Preview PDF -->
                            <a href="/ufc_v1/assessment/preview-pdf.php?id=<?= (int)$ass['id'] ?>"
                                target="_blank"
                                class="px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                                <i class="fa-regular fa-file-pdf text-amber-400 text-sm"></i>
                                <span>Preview PDF Report</span>
                            </a>

                            <!-- Download PDF -->
                            <a href="/ufc_v1/api/export_pdf.php?id=<?= (int)$ass['id'] ?>"
                                class="px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#122849] flex items-center gap-2.5 transition-colors">
                                <i class="fa-solid fa-file-pdf text-red-400 text-sm"></i>
                                <span>Download PDF</span>
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
<?php endif;
    return (string)ob_get_clean();
}

// Handle AJAX Live Search Request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'html'    => renderAssessmentRows($assessments, $searchQuery),
        'count'   => count($assessments),
    ]);
    exit;
}

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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
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

        <!-- Search Form with Live Search & Debounce -->
        <form action="" method="GET" id="live-search-form" class="w-full md:w-72">
            <input type="hidden" name="status" id="search-status-filter" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="relative flex items-center">
                <!-- Search Icon -->
                <svg class="w-4 h-4 text-slate-400 absolute left-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>

                <input type="text"
                    name="search"
                    id="live-search-input"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    placeholder="Search client, address, ref..."
                    autocomplete="off"
                    class="w-full pl-9 pr-8 py-1.5 bg-[#060f1e] border border-[#1e3e68] rounded-md text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c] focus:ring-1 focus:ring-[#c9a84c] transition-all">

                <!-- Loading Spinner Icon -->
                <div id="search-loading-spinner" class="hidden absolute right-2.5 flex items-center pointer-events-none">
                    <svg class="animate-spin h-3.5 w-3.5 text-[#c9a84c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </div>

                <!-- Clear Button -->
                <button type="button"
                    id="search-clear-btn"
                    class="<?= empty($searchQuery) ? 'hidden' : '' ?> absolute right-2.5 text-slate-400 hover:text-white transition-colors"
                    title="Clear search"
                    aria-label="Clear search">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Assessments Table -->
    <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl shadow-xl overflow-hidden relative">
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
                <tbody id="assessments-table-body" class="divide-y divide-[#1e3e68] transition-opacity duration-150">
                    <?= renderAssessmentRows($assessments, $searchQuery) ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Live Search Debounce Script -->
<script>
    (function() {
        const searchInput = document.getElementById('live-search-input');
        const searchForm = document.getElementById('live-search-form');
        const tableBody = document.getElementById('assessments-table-body');
        const clearBtn = document.getElementById('search-clear-btn');
        const spinner = document.getElementById('search-loading-spinner');
        const statusInput = document.getElementById('search-status-filter');

        if (!searchInput || !tableBody) return;

        // Configuration: Debounce delay in milliseconds to prevent excessive database queries on every keystroke
        const DEBOUNCE_DELAY_MS = 350;
        let debounceTimer = null;
        let currentAbortController = null;
        let lastQueriedValue = searchInput.value.trim();

        /**
         * Executes the search request to fetch updated table rows
         * @param {string} query - The search keyword
         */
        async function executeSearch(query) {
            const trimmedQuery = query.trim();

            // Update clear button visibility
            if (clearBtn) {
                clearBtn.classList.toggle('hidden', trimmedQuery.length === 0);
            }

            // Abort any ongoing in-flight request to prevent race conditions
            if (currentAbortController) {
                currentAbortController.abort();
            }
            currentAbortController = new AbortController();

            // Show spinner and subtle dimming on table
            if (spinner) spinner.classList.remove('hidden');
            if (clearBtn && trimmedQuery.length > 0) clearBtn.classList.add('hidden');
            tableBody.classList.add('opacity-60');

            const statusVal = statusInput ? statusInput.value : '';
            const params = new URLSearchParams();
            params.set('ajax', '1');
            if (statusVal) params.set('status', statusVal);
            if (trimmedQuery) params.set('search', trimmedQuery);

            try {
                const response = await fetch(`/ufc_v1/admin/assessments.php?${params.toString()}`, {
                    method: 'GET',
                    signal: currentAbortController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                if (data && typeof data.html === 'string') {
                    tableBody.innerHTML = data.html;
                    lastQueriedValue = trimmedQuery;

                    // Sync URL query string without reloading page
                    const browserUrl = new URL(window.location.href);
                    if (trimmedQuery) {
                        browserUrl.searchParams.set('search', trimmedQuery);
                    } else {
                        browserUrl.searchParams.delete('search');
                    }
                    if (statusVal) {
                        browserUrl.searchParams.set('status', statusVal);
                    } else {
                        browserUrl.searchParams.delete('status');
                    }
                    window.history.replaceState({}, '', browserUrl.toString());
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Live search request error:', err);
                }
            } finally {
                if (spinner) spinner.classList.add('hidden');
                if (clearBtn && searchInput.value.trim().length > 0) clearBtn.classList.remove('hidden');
                tableBody.classList.remove('opacity-60');
            }
        }

        /**
         * Debounced input handler (hook that stops DB search for few ms to avoid query per keypress)
         */
        function handleInput() {
            const query = searchInput.value;

            // Toggle clear button immediately while typing
            if (clearBtn) {
                clearBtn.classList.toggle('hidden', query.trim().length === 0);
            }

            // Reset previous debounce timer on every keystroke
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            // Hook delay before querying DB
            debounceTimer = setTimeout(() => {
                if (searchInput.value.trim() !== lastQueriedValue) {
                    executeSearch(searchInput.value);
                }
            }, DEBOUNCE_DELAY_MS);
        }

        // Input event for live debounced searching
        searchInput.addEventListener('input', handleInput);

        // Prevent full page reload on Enter, but execute search immediately
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (debounceTimer) clearTimeout(debounceTimer);
                executeSearch(searchInput.value);
            });
        }

        // Clear button action
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                if (debounceTimer) clearTimeout(debounceTimer);
                searchInput.focus();
                executeSearch('');
            });
        }
    })();

    // Global Row Action 3-Dot Mini Dropdown Menu Handler
    window.toggleRowDropdown = function(e, id) {
        if (e) e.stopPropagation();
        const targetMenu = document.getElementById(`row-dropdown-${id}`);
        const allMenus = document.querySelectorAll('[id^="row-dropdown-"]');

        allMenus.forEach(m => {
            if (m !== targetMenu) m.classList.add('hidden');
        });

        if (targetMenu) {
            targetMenu.classList.toggle('hidden');
        }
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.row-action-dropdown')) {
            const allMenus = document.querySelectorAll('[id^="row-dropdown-"]');
            allMenus.forEach(m => m.classList.add('hidden'));
        }
    });
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>