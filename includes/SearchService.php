<?php
/**
 * United Five Construction - Centralized Search Engine Service
 * ─────────────────────────────────────────────────────────────────────────────
 * Provides unified SQL clause building and standardized, reusable live-search
 * widgets with 0.6-second debouncing across all tables and dashboards.
 */

class SearchService
{
    /**
     * Build an SQL search clause with parameter bindings.
     * Supports multi-column searching with clean parameter sanitation.
     *
     * @param string $query The raw search query string from user input
     * @param array $columns List of SQL column expressions (e.g. ['a.client_name', 'a.project_name'])
     * @param string $prefix SQL clause prefix ('AND' or 'WHERE')
     * @return array ['sql' => string, 'params' => array]
     */
    public static function buildClause(string $query, array $columns, string $prefix = 'AND'): array
    {
        $trimmed = trim($query);
        if ($trimmed === '' || empty($columns)) {
            return ['sql' => '', 'params' => []];
        }

        // Clean extra whitespace
        $clean = preg_replace('/\s+/', ' ', $trimmed);
        $likeParam = "%{$clean}%";

        $conditions = [];
        $params = [];

        foreach ($columns as $col) {
            $conditions[] = "{$col} LIKE ?";
            $params[] = $likeParam;
        }

        $sql = " {$prefix} (" . implode(' OR ', $conditions) . ")";

        return [
            'sql'    => $sql,
            'params' => $params
        ];
    }

    /**
     * Renders a standardized search input widget.
     *
     * @param array $options Configuration options:
     *   - 'id' (string) HTML ID for the input (default: 'live-search-input')
     *   - 'name' (string) Form field name (default: 'search')
     *   - 'value' (string) Current search value
     *   - 'placeholder' (string) Placeholder text
     *   - 'target_table' (string) CSS selector of table tbody to swap (e.g. '#assessments-table-body')
     *   - 'form_id' (string) Optional parent form ID
     *   - 'endpoint' (string) Optional custom AJAX endpoint URL
     *   - 'debounce' (int) Milliseconds to debounce (default: 600)
     *   - 'wrapper_class' (string) Wrapper container classes
     * @return string HTML output
     */
    public static function renderInput(array $options = []): string
    {
        $id = $options['id'] ?? 'live-search-input';
        $name = $options['name'] ?? 'search';
        $val = htmlspecialchars($options['value'] ?? '', ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars($options['placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8');
        $targetTable = htmlspecialchars($options['target_table'] ?? '', ENT_QUOTES, 'UTF-8');
        $endpoint = htmlspecialchars($options['endpoint'] ?? '', ENT_QUOTES, 'UTF-8');
        $debounce = (int)($options['debounce'] ?? 600); // 0.6 seconds
        $wrapperClass = $options['wrapper_class'] ?? 'relative min-w-[240px] w-full';
        $formId = htmlspecialchars($options['form_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $hasVal = ($val !== '');

        $dataAttrs = "data-live-search=\"true\" data-debounce=\"{$debounce}\"";
        if (!empty($targetTable)) {
            $dataAttrs .= " data-target=\"{$targetTable}\"";
        }
        if (!empty($endpoint)) {
            $dataAttrs .= " data-endpoint=\"{$endpoint}\"";
        }
        if (!empty($formId)) {
            $dataAttrs .= " data-form=\"{$formId}\"";
        }

        ob_start();
        ?>
        <div class="search-widget-container <?= $wrapperClass ?>">
            <!-- Search Icon -->
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>

            <!-- Input Field -->
            <input type="text"
                   name="<?= $name ?>"
                   id="<?= $id ?>"
                   value="<?= $val ?>"
                   placeholder="<?= $placeholder ?>"
                   <?= $dataAttrs ?>
                   autocomplete="off"
                   class="w-full pl-8 pr-8 py-1.5 bg-[#060f1e] border border-[#1e3e68] rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#c9a84c] focus:ring-1 focus:ring-[#c9a84c] transition-all">

            <!-- Loading Spinner -->
            <div class="search-loading-spinner hidden absolute right-2.5 inset-y-0 flex items-center pointer-events-none">
                <svg class="animate-spin h-3.5 w-3.5 text-[#c9a84c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Clear Button -->
            <button type="button"
                    class="search-clear-btn <?= $hasVal ? '' : 'hidden' ?> absolute right-2.5 inset-y-0 flex items-center text-slate-400 hover:text-white transition-colors cursor-pointer"
                    title="Clear search"
                    aria-label="Clear search">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
