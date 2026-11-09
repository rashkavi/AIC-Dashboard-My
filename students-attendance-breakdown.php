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

        $counselorUserId = (int) $sessionUserObject['user_id'];
        if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($_GET['counselor_id'])) {
            $counselorUserId = (int) $_GET['counselor_id'];
        }
        $counselorUserIdEsc = Database::escape($counselorUserId);

        $timezone = new DateTimeZone("Asia/Colombo");
        $dateTime = new DateTime("now", $timezone);
        $today = $dateTime->format('Y-m-d');

        $defaultStart = $dateTime->format('Y-m-01');
        $defaultEnd   = $today;

        $intake_id = 0;
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id'])) {
            $intake_id = intval($_GET['intake-id']);
        }

        // Only used to decide whether to show the empty state.
        $hasStudents = Database::search("SELECT 1 FROM students_has_intakes WHERE status_status_id != '2' LIMIT 1")->num_rows > 0;

        $summaryCards = [
            ['id' => 'total-students', 'title' => 'Students', 'icon' => 'mdi:account-group-outline', 'period' => 'Across all intakes'],
            ['id' => 'lectures-held', 'title' => 'Lectures Held', 'icon' => 'mdi:calendar-check-outline', 'period' => 'In the selected period'],
            ['id' => 'avg-attendance', 'title' => 'Average Attendance', 'icon' => 'mdi:chart-line', 'period' => 'Present + Watched, across period'],
            ['id' => 'at-risk-count', 'title' => 'Needs Attention', 'icon' => 'mdi:alert-outline', 'period' => '3+ consecutive absences'],
        ];
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Attendance Breakdown - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/attendance.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                .bd-chart-canvas-wrap {
                    position: relative;
                    height: 280px;
                }

                .bd-range-toggle {
                    display: flex;
                }

                .bd-range-toggle button {
                    flex: 1;
                }

                .bd-at-risk-panel {
                    border: 1px solid #f1b0b0;
                    background: #fdf3f3;
                }

                .bd-at-risk-row {
                    background: #fff5f5;
                }

                .bd-streak-badge {
                    background: #ef4444;
                    color: #fff;
                    font-weight: 600;
                }

                .bd-at-risk-empty {
                    color: #6c757d;
                }

                .bd-context-line {
                    line-height: 1.3;
                }
            </style>
            <script>
                // Intake preselected via ?intake-id= (exact id filter). 0 = none.
                window.BD_FIXED_INTAKE_ID = <?= (int) $intake_id ?>;
            </script>
        </head>

        <body class="overflow-hidden" onload="initializeAttendanceBreakdown()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Attendance");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Attendance");
                                ?>
                            </div>

                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column" id="bd-real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-8">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Students Attendance Breakdown</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="counselor-dashboard-desc">Track your assigned students attendance, admissions activity, and performance at a glance</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ============================ FILTER BAR ============================ -->
                                    <div class="col-12 p-4 mb-4 component-theme">
                                        <div class="row gy-3 align-items-end">
                                            <div class="col-12 col-md-3 d-flex flex-column <?= $intake_id != 0 ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text mb-2 d-block" for="branch-selector">Branch</label>
                                                <select id="branch-selector" class="form-select custom-select" onchange="onBranchChanged()">
                                                    <option value="0" selected>All Branches</option>
                                                    <?php
                                                    $branches = Database::search("SELECT branch_id, branch FROM branches ORDER BY branch ASC");
                                                    foreach ($branches as $branch) { ?>
                                                        <option value="<?= (int) $branch["branch_id"] ?>"><?= htmlspecialchars($branch["branch"]) ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3 d-flex flex-column <?= $intake_id != 0 ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text mb-2 d-block" for="faculty-selector">Faculty</label>
                                                <select id="faculty-selector" class="form-select custom-select" onchange="onFacultyChanged()">
                                                    <option value="0" selected>All Faculties</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3 d-flex flex-column <?= $intake_id != 0 ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text mb-2 d-block" for="programme-selector">Programme</label>
                                                <select id="programme-selector" class="form-select custom-select" onchange="onProgrammeChanged()">
                                                    <option value="0" selected>All Programmes</option>
                                                </select>
                                            </div>

                                            <div class="col-12 col-md-3 <?= $intake_id != 0 ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text mb-2 d-block" for="intake-selector">Intake</label>
                                                <select id="intake-selector" class="form-select custom-select" onchange="onIntakeChanged()">
                                                    <option value="0" selected>All Intakes</option>
                                                </select>
                                            </div>

                                            <div class="col-12 col-md-3 <?= $intake_id != 0 ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text mb-2 d-block" for="subject-selector">Subject</label>
                                                <select id="subject-selector" class="form-select custom-select" onchange="loadBreakdown()">
                                                    <option value="0" selected>All Subjects</option>
                                                </select>
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Range Type</label>
                                                <div class="bd-range-toggle">
                                                    <button type="button" id="bd-mode-month" class="custom-btn" onclick="setBreakdownMode('month')">Month</button>
                                                    <button type="button" id="bd-mode-range" class="custom-outline-btn" onclick="setBreakdownMode('range')">Date Range</button>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-3" id="bd-month-wrapper">
                                                <label class="text-14 main-text mb-2 d-block">Month</label>
                                                <input type="month" id="bd-month-input" class="form-control custom-input" value="<?= $dateTime->format('Y-m') ?>" onchange="loadBreakdown()">
                                            </div>

                                            <div class="col-12 col-md-3 d-none" id="bd-range-wrapper">
                                                <label class="text-14 main-text mb-2 d-block">Date Range</label>
                                                <div class="d-flex gap-2">
                                                    <input type="date" id="bd-start-date" class="form-control custom-input" value="<?= $defaultStart ?>" onchange="loadBreakdown()">
                                                    <input type="date" id="bd-end-date" class="form-control custom-input" value="<?= $defaultEnd ?>" onchange="loadBreakdown()">
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-2">
                                                <button type="button" class="custom-btn w-100" onclick="loadBreakdown()">
                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon>
                                                    Refresh
                                                </button>
                                            </div>
                                        </div>
                                        <span id="bd-filter-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                    </div>

                                    <!-- ============================ EMPTY STATE ============================ -->
                                    <?php if (!$hasStudents) { ?>
                                        <div class="col-12 p-4 component-theme">
                                            <div class="d-flex flex-column align-items-center">
                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                <span class="text-14 sub-text mt-2">You don't have any assigned students yet, so there's no attendance breakdown to show.</span>
                                            </div>
                                        </div>
                                    <?php } else { ?>

                                        <!-- ============================ SUMMARY CARDS ============================ -->
                                        <div class="container-fluid p-0 mb-4">
                                            <div class="row g-3">
                                                <?php foreach ($summaryCards as $card) { ?>
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="stats-mini-card">
                                                            <div class="stats-mini-top">
                                                                <div class="stats-mini-left">
                                                                    <div class="stats-mini-icon">
                                                                        <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                                    </div>
                                                                    <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="stats-mini-body">
                                                                <div class="stats-mini-content">
                                                                    <div class="stats-mini-value" id="bd-value-<?= $card['id'] ?>">-</div>
                                                                    <div class="stats-mini-meta">
                                                                        <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <!-- ============================ CHARTS ============================ -->
                                        <div class="col-12 mb-4">
                                            <div class="row g-3">
                                                <div class="col-12 col-lg-8">
                                                    <div class="p-4 component-theme h-100">
                                                        <div class="text-14 main-text fw-medium mb-1">Daily Attendance Trend</div>
                                                        <div class="text-12 sub-text mb-3">% of held lecture slots marked Present/Watched each day, across your roster</div>
                                                        <div class="bd-chart-canvas-wrap">
                                                            <canvas id="bd-trend-chart"></canvas>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-4">
                                                    <div class="p-4 component-theme h-100">
                                                        <div class="text-14 main-text fw-medium mb-1">Status Breakdown</div>
                                                        <div class="text-12 sub-text mb-3">Present / Watched / Absent, over the period</div>
                                                        <div class="bd-chart-canvas-wrap">
                                                            <canvas id="bd-status-chart"></canvas>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <style>
                                            .bd-accordion .accordion-button {
                                                justify-content: flex-start;
                                                --bs-accordion-btn-color: var(--sub-topic);
                                            }

                                            .bd-accordion .accordion-button::after {
                                                order: -1;
                                                margin-left: 0;
                                                margin-right: 20px;
                                            }

                                            .bd-accordion .accordion-button:not(.collapsed) {
                                                box-shadow: none;
                                                background: transparent;
                                            }

                                            .bd-count-badge {
                                                font-size: 12px;
                                                font-weight: 600;
                                                padding: 2px 10px;
                                                border-radius: 20px;
                                                margin-left: 12px;
                                                background: #e9ecef;
                                                color: #343a40;
                                            }

                                            .bd-count-badge.danger {
                                                background: #ef4444;
                                                color: #fff;
                                            }
                                        </style>

                                        <!-- ============================ ACCORDIONS ============================ -->
                                        <div class="col-12 p-0">
                                            <div class="accordion accordion-flush bd-accordion" id="bdAccordion">

                                                <!-- NEEDS ATTENTION -->
                                                <div class="accordion-item mb-3 border-0 bd-at-risk-panel">
                                                    <h2 class="accordion-header d-flex align-items-center border-0 p-4">
                                                        <button class="accordion-button border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start"
                                                            type="button" data-bs-toggle="collapse" data-bs-target="#bd-collapse-at-risk"
                                                            aria-expanded="true" aria-controls="bd-collapse-at-risk">
                                                            <iconify-icon icon="mdi:alert-circle-outline" width="22" height="22" class="text-dark-red me-2"></iconify-icon>
                                                            <span class="d-flex flex-column">
                                                                <span class="text-14 main-text fw-medium">
                                                                    Needs Attention - 3+ Consecutive Absences
                                                                    <span class="bd-count-badge danger" id="bd-at-risk-count-badge">0</span>
                                                                </span>
                                                                <span class="text-12 sub-text fw-normal">Students whose most recent lectures in this period were all missed</span>
                                                            </span>
                                                        </button>
                                                    </h2>
                                                    <div id="bd-collapse-at-risk" class="accordion-collapse collapse">
                                                        <div class="accordion-body px-4 pb-4 pt-0">
                                                            <div class="table-responsive">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="bd-at-risk-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Faculty / Programme / Intake</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Consecutive Absences</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Last Attended</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="bd-at-risk-body">
                                                                        <tr>
                                                                            <td colspan="5" class="text-center py-4 text-14 bd-at-risk-empty">Loading...</td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- PER-STUDENT BREAKDOWN -->
                                                <div class="accordion-item mb-3 border-0 component-theme">
                                                    <h2 class="accordion-header d-flex align-items-center border-0 p-4">
                                                        <button class="accordion-button border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start"
                                                            type="button" data-bs-toggle="collapse" data-bs-target="#bd-collapse-students"
                                                            aria-expanded="true" aria-controls="bd-collapse-students">
                                                            <span class="d-flex flex-column">
                                                                <span class="text-14 main-text fw-medium">
                                                                    Per-Student Breakdown
                                                                    <span class="bd-count-badge" id="bd-students-count-badge">0</span>
                                                                </span>
                                                                <span class="text-12 sub-text fw-normal">Sorted by lowest attendance first, so you can spot who needs follow-up</span>
                                                            </span>
                                                        </button>
                                                    </h2>
                                                    <div id="bd-collapse-students" class="accordion-collapse collapse">
                                                        <div class="accordion-body px-4 pb-4 pt-0">
                                                            <div class="table-responsive">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="bd-students-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Faculty / Programme / Intake</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Present</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Watched</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Absent</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Lectures Held</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium text-center">Attendance %</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="bd-table-body">
                                                                        <tr id="bd-table-loading">
                                                                            <td colspan="9" class="text-center py-4 text-14 sub-text">Loading breakdown...</td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                    <?php } ?>

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
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

        </body>

        </html>
<?php
    }
}
?>