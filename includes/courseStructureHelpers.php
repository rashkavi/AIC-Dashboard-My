<?php

/**
 * Shared query-building + rendering helpers for the Course Structure
 * (subjects -> levels -> intakes -> programmes -> faculties -> branches) filter/search table.
 *
 * Used by both course-structure.php (initial page load) and
 * api/filterCourseStructure.php (AJAX filter/search/pagination).
 */

const COURSE_STRUCTURE_BASE_FROM = "
    FROM `subjects`
    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
    INNER JOIN `branches` ON `faculties`.`branches_branch_id` = `branches`.`branch_id`
";

/**
 * Builds a WHERE clause from the current filter selections.
 * Pass $exclude to leave one filter out — this is how the facet (dropdown option)
 * queries stay "cascading": e.g. when computing distinct Faculties, we apply every
 * filter EXCEPT the faculty filter itself, so the Faculty dropdown still shows all
 * faculties valid for the current Branch/Programme/Intake/Level/Subject selection,
 * not just the one already picked.
 *
 * @param array  $filters ['text','branch','faculty','programme','intake','level']
 * @param string $exclude which key to skip, or '' to apply all filters
 */
function buildCourseStructureWhere($filters, $exclude = '')
{
    $conditions = [];

    if ($exclude !== 'text' && $filters['text'] !== '') {
        $safeText = addslashes($filters['text']);
        $conditions[] = "`subjects`.`subject` LIKE '%" . $safeText . "%'";
    }
    if ($exclude !== 'branch' && (int)$filters['branch'] > 0) {
        $conditions[] = "`branches`.`branch_id` = " . (int)$filters['branch'];
    }
    if ($exclude !== 'faculty' && (int)$filters['faculty'] > 0) {
        $conditions[] = "`faculties`.`faculty_id` = " . (int)$filters['faculty'];
    }
    if ($exclude !== 'programme' && (int)$filters['programme'] > 0) {
        $conditions[] = "`programmes`.`programme_id` = " . (int)$filters['programme'];
    }
    if ($exclude !== 'intake' && (int)$filters['intake'] > 0) {
        $conditions[] = "`intakes`.`intake_id` = " . (int)$filters['intake'];
    }
    if ($exclude !== 'level' && (int)$filters['level'] > 0) {
        $conditions[] = "`levels`.`level_id` = " . (int)$filters['level'];
    }

    return count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";
}

/**
 * Returns the six distinct-value option lists (subject/level/intake/programme/faculty/branch)
 * for repopulating the selectors after every filter/search/pagination action.
 */
function getCourseStructureOptions($filters)
{
    $optionQueries = [
        'subject'   => ['exclude' => 'text',      'table' => 'subjects',   'idCol' => 'subject_id',   'nameCol' => 'subject'],
        'level'     => ['exclude' => 'level',      'table' => 'levels',     'idCol' => 'level_id',     'nameCol' => 'level'],
        'intake'    => ['exclude' => 'intake',     'table' => 'intakes',    'idCol' => 'intake_id',    'nameCol' => 'intake'],
        'programme' => ['exclude' => 'programme',  'table' => 'programmes', 'idCol' => 'programme_id', 'nameCol' => 'programme'],
        'faculty'   => ['exclude' => 'faculty',    'table' => 'faculties',  'idCol' => 'faculty_id',   'nameCol' => 'faculty'],
        'branch'    => ['exclude' => 'branch',     'table' => 'branches',   'idCol' => 'branch_id',    'nameCol' => 'branch'],
    ];

    $options = [];

    foreach ($optionQueries as $key => $cfg) {
        $query = "SELECT DISTINCT `" . $cfg['table'] . "`.`" . $cfg['idCol'] . "`, `" . $cfg['table'] . "`.`" . $cfg['nameCol'] . "`"
            . COURSE_STRUCTURE_BASE_FROM
            . buildCourseStructureWhere($filters, $cfg['exclude'])
            . " ORDER BY `" . $cfg['table'] . "`.`" . $cfg['nameCol'] . "` ASC";

        $result = Database::search($query);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = ['id' => $row[$cfg['idCol']], 'name' => $row[$cfg['nameCol']]];
        }
        $options[$key] = $rows;
    }

    return $options;
}

function getCourseStructureOptionsJson($filters)
{
    return json_encode(getCourseStructureOptions($filters));
}

/**
 * Renders the <table> + pagination markup for the course structure list.
 * Echoes directly (used both inline on the page and captured via ob_start() in the API).
 */
function renderCourseStructureTable($filters, $pageno, $result_per_page, $sort_subject, $sort_date, $type)
{
    $selectFields = "
        `subjects`.`subject_id`, `subjects`.`subject`, `subjects`.`max_course_hours`, `subjects`.`date_added`,
        `levels`.`level`,
        `intakes`.`intake`,
        `programmes`.`programme`,
        `faculties`.`faculty`,
        `branches`.`branch`
    ";

    $query = "SELECT " . $selectFields . COURSE_STRUCTURE_BASE_FROM . buildCourseStructureWhere($filters);

    if ($sort_subject != 0) {
        $query .= " ORDER BY `subjects`.`subject` " . ($sort_subject == 1 ? 'ASC' : 'DESC');
    } elseif ($sort_date != 0) {
        $query .= " ORDER BY `subjects`.`date_added` " . ($sort_date == 1 ? 'ASC' : 'DESC');
    } else {
        $query .= " ORDER BY `subjects`.`date_added` DESC";
    }

    $countResult = Database::search($query);
    $results_count = $countResult->num_rows;
    $result_per_page = $result_per_page > 0 ? $result_per_page : 10;
    $number_of_pages = $results_count > $result_per_page ? ceil($results_count / $result_per_page) : 1;
    $page_results = ($pageno - 1) * $result_per_page;

    $rows = Database::search($query . " LIMIT " . $result_per_page . " OFFSET " . $page_results);
?>
    <table class="table bl-solid m-0 p-0 g-0" id="course-structure-table">
        <thead>
            <tr class="br-t-5 align-content-center">
                <th scope="col" class="px-4 py-3 bg-table-header align-content-center cursor-pointer">

                    <div class="d-flex align-items-center w-100">
                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" <?= $type == 1 ? 'onchange="lecTableCheckEvents()"' : 'onchange="assignmentTableCheckEvents()"' ?>>

                        <span class="text-14 main-text fw-medium">Branch</span>
                    </div>
                </th>
                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Faculty</th>
                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Programme</th>
                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Intake</th>
                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Level</th>
                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <span class="text-14 main-text fw-medium">Subject</span>
                        <iconify-icon icon="<?php echo $sort_subject == 0 ? 'flowbite:chevron-sort-outline' : ($sort_subject == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill'); ?>" width="20" height="20" class="sub-text"></iconify-icon>
                    </div>
                </th>

            </tr>
        </thead>
        <tbody id="course-structure-table-data">
            <?php if ($rows->num_rows > 0) : ?>
                <?php while ($data = $rows->fetch_assoc()) : ?>
                    <tr>
                        <td class="px-4 py-3 align-middle">
                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["subject_id"]; ?>" <?php echo $type == 1 ? 'onchange="lecCheckRowEvents(' . $data["subject_id"] . ')"' : 'onchange="assignmentCheckRowEvents(' . $data["subject_id"] . ')"' ?>>
                            <span class="text-14 main-text fw-medium"><?php echo htmlspecialchars($data["branch"]); ?></span>
                        </td>
                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo htmlspecialchars($data["faculty"]); ?></span></td>
                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo htmlspecialchars($data["programme"]); ?></span></td>
                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo htmlspecialchars($data["intake"]); ?></span></td>
                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo htmlspecialchars($data["level"]); ?></span></td>
                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo htmlspecialchars($data["subject"]); ?></span></td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr class="justify-content-center align-middle">
                    <td colspan="7" class="align-middle justify-content-center">
                        <div class="d-flex flex-column align-items-center">
                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
            <div class="row p-4 align-items-center">
                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCoursePagination('previousPage')">
                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                    <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                </button>
                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                    <ul class="pagination-list d-flex mb-0">
                        <?php
                        $shudderPages1 = false;
                        $shudderPages2 = false;

                        for ($i = 1; $i <= $number_of_pages; $i++) {
                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                $activeClass = $pageno == $i ? 'active' : '';
                                $action = $pageno == $i ? '' : "onclick=\"handleCoursePagination('goToPage', '{$i}')\"";
                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                            } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                $shudderPages1 = true;
                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                            } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                $shudderPages2 = true;
                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                            }
                        }
                        ?>
                    </ul>
                </div>
                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCoursePagination('nextPage', null, '<?php echo $number_of_pages; ?>')">
                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                </button>
            </div>
        </nav>
    </div>
<?php
}
