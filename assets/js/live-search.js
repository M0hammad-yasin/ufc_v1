/**
 * UFC Live Search Engine — Centralized Debounced AJAX Search
 * ─────────────────────────────────────────────────────────────────────────────
 * Automatically initializes on any element with [data-live-search="true"].
 * Configuration is driven entirely by data-* attributes on the input:
 *
 *   data-live-search="true"        Required. Activates this engine on the element.
 *   data-debounce="600"            Delay in ms before firing query (default: 600).
 *   data-target="#table-body-id"   CSS selector of the <tbody> to swap.
 *   data-endpoint="/path/to.php"   AJAX endpoint. Defaults to current page URL.
 *   data-form="form-id"            Optional form ID whose other inputs are serialized
 *                                  as additional filters alongside the search query.
 *
 * Usage example from PHP (via SearchService::renderInput):
 *   <input type="text" data-live-search="true" data-target="#my-table-body" ...>
 *
 * The engine:
 *   1. Waits 600ms after the last keystroke before querying.
 *   2. Aborts any in-flight request to prevent race conditions.
 *   3. Appends ?ajax=1&search=... to the endpoint.
 *   4. Swaps the innerHTML of the target tbody with server HTML.
 *   5. Updates window.history.replaceState() to keep URL in sync.
 *   6. Shows a spinner while loading and hides it when done.
 *   7. The Clear (X) button resets and re-fires an empty search.
 */
(function () {
    'use strict';

    const DEFAULT_DEBOUNCE = 600;

    /**
     * Initialise a single live-search input element.
     * @param {HTMLInputElement} input
     */
    function initSearchInput(input) {
        if (input.dataset.liveSearchInit) return; // already initialised
        input.dataset.liveSearchInit = '1';

        const debounceMs   = parseInt(input.dataset.debounce  || DEFAULT_DEBOUNCE, 10);
        const targetSel    = input.dataset.target   || null;
        const endpoint     = input.dataset.endpoint || window.location.pathname;
        const formId       = input.dataset.form     || null;

        const tableBody    = targetSel ? document.querySelector(targetSel) : null;
        const formEl       = formId ? document.getElementById(formId) : input.closest('form');
        const container    = input.closest('.search-widget-container');
        const spinner      = container ? container.querySelector('.search-loading-spinner') : null;
        const clearBtn     = container ? container.querySelector('.search-clear-btn')     : null;

        let debounceTimer = null;
        let controller    = null;
        let lastQuery     = input.value.trim();

        // Keep clear button initial state
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', input.value.trim() === '');
        }

        /**
         * Core AJAX fetch, table swap, and URL update.
         * @param {string} rawQuery
         */
        async function runSearch(rawQuery) {
            const query = rawQuery.trim();

            // Update clear button
            if (clearBtn) clearBtn.classList.toggle('hidden', query === '');

            // Abort previous in-flight request
            if (controller) controller.abort();
            controller = new AbortController();

            // Visual feedback
            if (spinner)    spinner.classList.remove('hidden');
            if (clearBtn && query !== '') clearBtn.classList.add('hidden');
            if (tableBody)  tableBody.classList.add('opacity-50', 'pointer-events-none');

            // Build query string — pick up extra filters from sibling form inputs
            const params = new URLSearchParams();
            params.set('ajax', '1');
            if (query) params.set('search', query);

            if (formEl) {
                const formData = new FormData(formEl);
                for (const [key, value] of formData.entries()) {
                    if (key !== input.name && key !== 'ajax' && value !== '') {
                        params.set(key, value);
                    }
                }
            }

            try {
                const res = await fetch(`${endpoint}?${params.toString()}`, {
                    signal: controller.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();

                if (tableBody && typeof data.html === 'string') {
                    tableBody.innerHTML = data.html;
                }

                lastQuery = query;

                // Sync browser URL without reload
                const url = new URL(window.location.href);
                if (query) {
                    url.searchParams.set('search', query);
                } else {
                    url.searchParams.delete('search');
                }
                // Mirror other active filters into the URL
                if (formEl) {
                    const formData = new FormData(formEl);
                    for (const [key, value] of formData.entries()) {
                        if (key !== input.name && key !== 'ajax') {
                            if (value) {
                                url.searchParams.set(key, value);
                            } else {
                                url.searchParams.delete(key);
                            }
                        }
                    }
                }
                window.history.replaceState({}, '', url.toString());

            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.warn('[UFC LiveSearch] Fetch error:', err.message);
                }
            } finally {
                if (spinner)    spinner.classList.add('hidden');
                if (clearBtn)   clearBtn.classList.toggle('hidden', input.value.trim() === '');
                if (tableBody)  tableBody.classList.remove('opacity-50', 'pointer-events-none');
            }
        }

        // ── Debounced input handler ──────────────────────────────────────────
        input.addEventListener('input', function () {
            if (clearBtn) clearBtn.classList.toggle('hidden', this.value.trim() === '');

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (this.value.trim() !== lastQuery) {
                    runSearch(this.value);
                }
            }, debounceMs);
        });

        // ── Intercept form submit → run immediately, no page reload ──────────
        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                clearTimeout(debounceTimer);
                runSearch(input.value);
            });
        }

        // ── Clear button ─────────────────────────────────────────────────────
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                clearTimeout(debounceTimer);
                input.focus();
                runSearch('');
            });
        }
    }

    /**
     * Scan the page and initialise all live-search inputs.
     */
    function initAll() {
        document.querySelectorAll('[data-live-search="true"]').forEach(initSearchInput);
    }

    // Initialise on DOMContentLoaded (and immediately if already loaded)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose globally so pages can re-init after dynamic content injection
    window.UFCLiveSearch = { init: initAll, initInput: initSearchInput };

})();
