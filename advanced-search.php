<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Advanced Search - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/advanced-search.css?v=<?= Project::getVersion() ?>">
            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body>
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "./includes/side-menu.php";
                        load_side_menu::load("Advanced Search");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Advanced Search");
                                ?>
                            </div>

                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>

                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 col-md-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="">Advanced Search</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="">Search across branches, faculties, programmes, batches and students — all in one place.</span>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <?php
                                    /**
                                     * advanced-search.php
                                     * ---------------------------------------------------------------------
                                     * Drop the <main> block below into your existing page layout (the same
                                     * header/sidebar include every other admin page uses). Only the markup
                                     * inside <main class="gsearch-page"> is new — everything else is your
                                     * project's normal shell.
                                     * ---------------------------------------------------------------------
                                     */
                                    // require_once __DIR__ . '/../config/config.php';
                                    // require_once __DIR__ . '/../partials/header.php';
                                    // require_once __DIR__ . '/../partials/sidebar.php';
                                    ?>

                                    <div class="col-12 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <main class="gsearch-page app-wrapper w-100 p-4">

                                                <!-- Search bar -->
                                                <div class="gsearch-wrap">
                                                    <div class="gsearch-input-box">
                                                        <iconify-icon icon="mdi:magnify"></iconify-icon>
                                                        <input
                                                            type="text"
                                                            id="gsearchInput"
                                                            autocomplete="off"
                                                            placeholder="Search branches, programmes, batches, students… e.g. &quot;Batch 5&quot;, &quot;AIC Campus&quot;, &quot;John Silva&quot;">
                                                        <span class="gsearch-input-hint">Enter ↵</span>
                                                    </div>

                                                    <!-- Live typeahead dropdown, grouped by category -->
                                                    <div id="gsearchDropdown" class="gsearch-dropdown"></div>
                                                </div>

                                                <!-- Category filter chips -->
                                                <div class="gsearch-chips" id="gsearchChips">
                                                    <div class="chip selected" data-type="all">
                                                        <iconify-icon icon="mdi:view-grid-outline"></iconify-icon>
                                                        <span class="chip-label">All</span>
                                                    </div>
                                                    <div class="chip" data-type="branch">
                                                        <iconify-icon icon="mdi:office-building"></iconify-icon>
                                                        <span class="chip-label">Branches</span>
                                                    </div>
                                                    <div class="chip" data-type="faculty">
                                                        <iconify-icon icon="mdi:bank-outline"></iconify-icon>
                                                        <span class="chip-label">Faculties</span>
                                                    </div>
                                                    <div class="chip" data-type="programme">
                                                        <iconify-icon icon="mdi:book-open-page-variant"></iconify-icon>
                                                        <span class="chip-label">Programmes</span>
                                                    </div>
                                                    <div class="chip" data-type="intake">
                                                        <iconify-icon icon="mdi:account-group-outline"></iconify-icon>
                                                        <span class="chip-label">Batches</span>
                                                    </div>
                                                    <div class="chip" data-type="student">
                                                        <iconify-icon icon="mdi:account-outline"></iconify-icon>
                                                        <span class="chip-label">Students</span>
                                                    </div>
                                                </div>

                                                <!-- Results -->
                                                <div id="gsearchResultsHeader" class="gsearch-results-header"></div>

                                                <!-- Skeleton (shown while a full search is loading) -->
                                                <div id="gsearchSkeleton" class="gsearch-results-list d-none">
                                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                                        <div class="gsearch-skeleton-row">
                                                            <div class="gsearch-skeleton-block gsearch-skeleton-icon"></div>
                                                            <div class="gsearch-skeleton-lines">
                                                                <div class="gsearch-skeleton-block gsearch-skeleton-line"></div>
                                                                <div class="gsearch-skeleton-block gsearch-skeleton-line short"></div>
                                                            </div>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>

                                                <div id="gsearchResultsList" class="gsearch-results-list d-none"></div>

                                                <button type="button" id="gsearchLoadMore" class="gsearch-load-more d-none">
                                                    Load more results
                                                </button>

                                                <!-- Empty state -->
                                                <div id="gsearchEmptyState" class="gsearch-empty-state d-none">
                                                    <iconify-icon icon="mdi:file-search-outline"></iconify-icon>
                                                    <div style="font-weight:600; color: var(--main-text-color);">No results found</div>
                                                    <div style="font-size: var(--font-size-13);">Try a different keyword, or check spelling.</div>
                                                </div>

                                            </main>

                                            <!-- Batch roster modal -->
                                            <div class="modal fade" id="gsearchRosterModal" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-scrollable modal-md">
                                                    <div class="modal-content" style="border-radius:0; border:1px solid var(--line-color); background: var(--custom-white);">
                                                        <div class="modal-header" style="border-bottom:1px solid var(--line-color);">
                                                            <h6 class="modal-title" id="gsearchRosterTitle" style="color: var(--main-text-color); font-weight:700;"></h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body" id="gsearchRosterBody"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                <div class="flex-grow-1"></div>
                                <footer>
                                    <?php include './includes/footer.php' ?>
                                </footer>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/advanced-search.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
?>