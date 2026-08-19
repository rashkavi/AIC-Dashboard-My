/**
 * advanced-search.js
 * ---------------------------------------------------------------------
 * Drives the universal search page:
 *   - Debounced "instant" dropdown while typing (grouped by category)
 *   - Category filter chips (All / Branches / Faculties / Programmes /
 *     Batches / Students) that switch into a paginated "full" list view
 *   - A clean, single-line result row per item with a breadcrumb path
 *   - Batch rows expand to preview a handful of enrolled students and
 *     offer a "View all" roster
 *
 * Requires: API_ENDPOINT to point at advanced-search-api.php, and the
 * existing project-wide `showToast(type, message)` helper (used the same
 * way it's used across the rest of the system) for error feedback.
 * ---------------------------------------------------------------------
 */

(function () {
    'use strict';

    // ---- CONFIG -------------------------------------------------------
    // Adjust to your actual deployed path, e.g. "/admin/api/advanced-search-api.php"
    const API_ENDPOINT = 'api/advancedSearch.php';

    // Adjust these to the real listing pages already in your project so
    // clicking a result actually takes the user somewhere useful.
    const LINKS = {
        branch:    (row) => `faculties?branch-id=${row.branch_id}`,
        faculty:   (row) => `programmes?faculty-id=${row.faculty_id}`,
        programme: (row) => `intakes?programme-id=${row.programme_id}`,
        intake:    (row) => `intake-details?intake-id=${row.intake_id}`,
        student:   (row) => `student-details?student-id=${row.student_id}`,
    };

    const CATEGORY_META = {
        branch:    { label: 'Branches',   icon: 'mdi:office-building',        group: 'branches' },
        faculty:   { label: 'Faculties',  icon: 'mdi:bank-outline',           group: 'faculties' },
        programme: { label: 'Programmes', icon: 'mdi:book-open-page-variant', group: 'programmes' },
        intake:    { label: 'Batches',    icon: 'mdi:account-group-outline',  group: 'intakes' },
        student:   { label: 'Students',   icon: 'mdi:account-outline',        group: 'students' },
    };

    // ---- DOM ------------------------------------------------------------
    const input       = document.getElementById('gsearchInput');
    const dropdown     = document.getElementById('gsearchDropdown');
    const chipsWrap     = document.getElementById('gsearchChips');
    const resultsWrap   = document.getElementById('gsearchResultsList');
    const resultsHeader = document.getElementById('gsearchResultsHeader');
    const loadMoreBtn   = document.getElementById('gsearchLoadMore');
    const emptyState    = document.getElementById('gsearchEmptyState');
    const skeleton       = document.getElementById('gsearchSkeleton');

    let debounceTimer = null;
    let activeType = 'all';
    let currentPage = 1;
    let lastQuery = '';
    let dropdownAbort = null;
    let listAbort = null;

    if (!input) return; // page not present, script safely no-ops

    // ---- Helpers --------------------------------------------------------
    function debounce(fn, delay) {
        return function (...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fn.apply(null, args), delay);
        };
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function breadcrumb(row) {
        const parts = [];
        if (row.branch) parts.push(row.branch);
        if (row.faculty) parts.push(row.faculty);
        if (row.programme) parts.push(row.programme);
        if (row.intake && row.type !== 'intake') parts.push(row.intake);
        return parts.map(escapeHtml).join(' <span class="gsearch-crumb-sep">›</span> ');
    }

    function highlightTitle(row) {
        switch (row.type) {
            case 'branch': return row.branch;
            case 'faculty': return row.faculty;
            case 'programme': return row.programme;
            case 'intake': return row.intake;
            case 'student': return row.full_name;
            default: return '';
        }
    }

    async function fetchResults(query, type, mode, page, signal) {
        const params = new URLSearchParams({ q: query, type, mode, page: String(page) });
        const res = await fetch(`${API_ENDPOINT}?${params.toString()}`, { signal });
        if (!res.ok) throw new Error('Search request failed');
        return res.json();
    }

    // ---- Instant dropdown ------------------------------------------------
    function renderDropdown(data) {
        if (!data.success) {
            dropdown.innerHTML = `<div class="gsearch-dropdown-empty">${escapeHtml(data.message || 'Search failed.')}</div>`;
            dropdown.classList.add('show');
            return;
        }

        const groups = [
            ['branches', 'branch'],
            ['faculties', 'faculty'],
            ['programmes', 'programme'],
            ['intakes', 'intake'],
            ['students', 'student'],
        ];

        let html = '';
        let totalShown = 0;

        groups.forEach(([groupKey, typeKey]) => {
            const rows = data.results[groupKey] || [];
            if (!rows.length) return;
            totalShown += rows.length;
            const meta = CATEGORY_META[typeKey];

            html += `<div class="gsearch-group">
                <div class="gsearch-group-title">
                    <iconify-icon icon="${meta.icon}"></iconify-icon> ${meta.label}
                    <span class="gsearch-group-count">${data.counts[groupKey]}</span>
                </div>`;

            rows.forEach((row) => {
                html += renderRowHtml(row, true);
            });

            if (data.has_more[groupKey]) {
                html += `<button type="button" class="gsearch-view-all-link" data-type="${typeKey}">
                    View all ${data.counts[groupKey]} ${meta.label.toLowerCase()} <iconify-icon icon="mdi:arrow-right"></iconify-icon>
                </button>`;
            }
            html += `</div>`;
        });

        if (totalShown === 0) {
            html = `<div class="gsearch-dropdown-empty">
                <iconify-icon icon="mdi:magnify-close"></iconify-icon>
                No matches for "<strong>${escapeHtml(data.query)}</strong>"
            </div>`;
        }

        dropdown.innerHTML = html;
        dropdown.classList.add('show');
    }

    const runInstantSearch = debounce(async function (query) {
        if (!query.trim()) {
            dropdown.classList.remove('show');
            dropdown.innerHTML = '';
            return;
        }
        if (dropdownAbort) dropdownAbort.abort();
        dropdownAbort = new AbortController();

        dropdown.innerHTML = `<div class="gsearch-dropdown-loading">
            <div class="gsearch-spinner"></div> Searching…
        </div>`;
        dropdown.classList.add('show');

        try {
            const data = await fetchResults(query, 'all', 'instant', 1, dropdownAbort.signal);
            renderDropdown(data);
        } catch (err) {
            console.error(err);
            if (err.name === 'AbortError') return;
            if (typeof showToast === 'function') showToast('error', 'Search failed. Please try again.');
        }
    }, 300);

    input.addEventListener('input', (e) => {
        runInstantSearch(e.target.value);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            dropdown.classList.remove('show');
            runFullSearch(input.value, activeType, 1);
        }
        if (e.key === 'Escape') {
            dropdown.classList.remove('show');
        }
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== input) {
            dropdown.classList.remove('show');
        }
    });

    dropdown.addEventListener('click', (e) => {
        const viewAllBtn = e.target.closest('.gsearch-view-all-link');
        if (viewAllBtn) {
            const type = viewAllBtn.dataset.type;
            setActiveChip(type);
            dropdown.classList.remove('show');
            runFullSearch(input.value, type, 1);
            return;
        }
        const row = e.target.closest('.gsearch-row');
        if (row) {
            navigateToRow(row.dataset.type, JSON.parse(row.dataset.row));
        }
    });

    // ---- Filter chips -----------------------------------------------------
    if (chipsWrap) {
        chipsWrap.addEventListener('click', (e) => {
            const chip = e.target.closest('.chip[data-type]');
            if (!chip) return;
            setActiveChip(chip.dataset.type);
            runFullSearch(input.value, chip.dataset.type, 1);
        });
    }

    function setActiveChip(type) {
        activeType = type;
        if (!chipsWrap) return;
        chipsWrap.querySelectorAll('.chip').forEach((c) => {
            c.classList.toggle('selected', c.dataset.type === type);
        });
    }

    // ---- Full results list --------------------------------------------------
    function renderRowHtml(row, compact) {
        const meta = CATEGORY_META[row.type];
        const crumbHtml = breadcrumb(row);
        const rowJson = escapeHtml(JSON.stringify(row));

        let rightMeta = '';
        if (row.type === 'branch') {
            rightMeta = `<span class="status-badge">${row.faculty_count} faculties</span>
                <span class="status-badge status-success">${row.student_count} students</span>`;
        } else if (row.type === 'faculty') {
            rightMeta = `<span class="status-badge">${row.programme_count} programmes</span>
                <span class="status-badge status-success">${row.student_count} students</span>`;
        } else if (row.type === 'programme') {
            rightMeta = `<span class="status-badge">${row.intake_count} batches</span>
                <span class="status-badge status-success">${row.student_count} students</span>`;
        } else if (row.type === 'intake') {
            rightMeta = `<span class="status-badge status-success">${row.student_count} students</span>`;
        } else if (row.type === 'student') {
            rightMeta = row.batch_count > 1
                ? `<span class="status-badge">${row.batch_count} batches</span>`
                : '';
        }

        let previewHtml = '';
        if (!compact && row.type === 'intake' && row.sample_students && row.sample_students.length) {
            const names = row.sample_students.map((n) => `<span class="gsearch-preview-chip">${escapeHtml(n)}</span>`).join('');
            const more = row.student_count > row.sample_students.length
                ? `<button type="button" class="gsearch-preview-more" data-intake-id="${row.intake_id}" data-programme="${escapeHtml(row.intake)}">
                       +${row.student_count - row.sample_students.length} more — view all
                   </button>`
                : '';
            previewHtml = `<div class="gsearch-preview-row">${names}${more}</div>`;
        }

        return `
            <div class="gsearch-row" data-type="${row.type}" data-row='${rowJson}'>
                <div class="gsearch-row-icon gsearch-row-icon-${row.type}">
                    <iconify-icon icon="${meta.icon}"></iconify-icon>
                </div>
                <div class="gsearch-row-body">
                    <div class="gsearch-row-title">${escapeHtml(highlightTitle(row))}</div>
                    ${crumbHtml ? `<div class="gsearch-row-crumb">${crumbHtml}</div>` : ''}
                    ${previewHtml}
                </div>
                <div class="gsearch-row-meta">${rightMeta}</div>
                <iconify-icon class="gsearch-row-chevron" icon="mdi:chevron-right"></iconify-icon>
            </div>
        `;
    }

    async function runFullSearch(query, type, page) {
        if (!query.trim()) return;
        lastQuery = query;
        currentPage = page;

        if (listAbort) listAbort.abort();
        listAbort = new AbortController();

        resultsWrap.classList.add('d-none');
        emptyState.classList.add('d-none');
        skeleton.classList.remove('d-none');
        loadMoreBtn.classList.add('d-none');

        try {
            const data = await fetchResults(query, type, 'full', page, listAbort.signal);
            skeleton.classList.add('d-none');

            if (!data.success) {
                if (typeof showToast === 'function') showToast('warning', data.message || 'Search failed.');
                return;
            }

            const groupKey = type === 'all' ? null : CATEGORY_META[type].group;
            const allRows = groupKey
                ? (data.results[groupKey] || [])
                : Object.values(data.results).flat();
            const totalCount = groupKey
                ? data.counts[groupKey]
                : Object.values(data.counts).reduce((a, b) => a + b, 0);

            if (page === 1) resultsWrap.innerHTML = '';
            allRows.forEach((row) => {
                resultsWrap.insertAdjacentHTML('beforeend', renderRowHtml(row, false));
            });
            resultsWrap.classList.remove('d-none');

            resultsHeader.textContent = `${totalCount} result${totalCount === 1 ? '' : 's'} for "${query}"`;

            const moreAvailable = groupKey
                ? data.has_more[groupKey]
                : Object.values(data.has_more).some(Boolean);
            loadMoreBtn.classList.toggle('d-none', !moreAvailable);

            if (allRows.length === 0 && page === 1) {
                emptyState.classList.remove('d-none');
            }
        } catch (err) {
            skeleton.classList.add('d-none');
            if (err.name === 'AbortError') return;
            if (typeof showToast === 'function') showToast('error', 'Search failed. Please try again.');
        }
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            runFullSearch(lastQuery, activeType, currentPage + 1);
        });
    }

    if (resultsWrap) {
        resultsWrap.addEventListener('click', (e) => {
            const moreBtn = e.target.closest('.gsearch-preview-more');
            if (moreBtn) {
                e.stopPropagation();
                openBatchRoster(parseInt(moreBtn.dataset.intakeId, 10), moreBtn.dataset.programme);
                return;
            }
            const row = e.target.closest('.gsearch-row');
            if (row) {
                navigateToRow(row.dataset.type, JSON.parse(row.dataset.row));
            }
        });
    }

    function navigateToRow(type, row) {
        const linkFn = LINKS[type];
        if (linkFn) {
            window.location.href = linkFn(row);
        }
    }

    // ---- Batch roster (drill-down) ---------------------------------------
    async function openBatchRoster(intakeId, batchName) {
        const modalEl = document.getElementById('gsearchRosterModal');
        const modalBody = document.getElementById('gsearchRosterBody');
        const modalTitle = document.getElementById('gsearchRosterTitle');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        modalTitle.textContent = `Students in ${batchName}`;
        modalBody.innerHTML = `<div class="gsearch-dropdown-loading"><div class="gsearch-spinner"></div> Loading roster…</div>`;
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        try {
            // Roster requests are keyed by intake_id, not a keyword — pass the
            // batch name through as q purely so the API's "q required" guard
            // is satisfied on servers where that check isn't relaxed.
            const params = new URLSearchParams({ q: batchName, type: 'student', mode: 'full', page: '1', intake_id: String(intakeId) });
            const res = await fetch(`${API_ENDPOINT}?${params.toString()}`);
            const rosterData = await res.json();

            console.log(rosterData);

            const rows = (rosterData.results && rosterData.results.students) || [];
            if (!rows.length) {
                modalBody.innerHTML = `<div class="gsearch-dropdown-empty">No students found in this batch.</div>`;
                return;
            }
            modalBody.innerHTML = rows.map((s) => `
                <div class="gsearch-roster-row">
                    <div class="gsearch-row-icon gsearch-row-icon-student"><iconify-icon icon="mdi:account-outline"></iconify-icon></div>
                    <div class="gsearch-row-body">
                        <div class="gsearch-row-title">${escapeHtml(s.full_name)}</div>
                        <div class="gsearch-row-crumb">${escapeHtml(s.mobile || '')} ${s.nic ? '• ' + escapeHtml(s.nic) : ''}</div>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            modalBody.innerHTML = `<div class="gsearch-dropdown-empty">Couldn't load the roster. Please try again.</div>`;
        }
    }
})();
