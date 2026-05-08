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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['subject-id']) && !empty($_GET['subject-id']))) {
            header("Location: ./selected-subject");
        } else {

            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT `subject_id`,`faculty`,`faculty_id`,`programme`,`programme_id`,`intake`,`intake_id`,`level`,`level_id`,`subject`,`subject_id` FROM `subjects`
            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`  
            WHERE `subject_id` = '" . $id . "'");

            if ($subject->num_rows == 0) {
                header("Location: ./dashboard");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject', 29, $id))) {
                    header("Location: ./subject-details?subject-id=" . $id);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Subject Grade Calculation Structure - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

                    </head>

                    <body class="overflow-hidden" onload="initializeAddSubjectGradeCalculationStructure();">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("Course Overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Course Overview");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="add-grade-calculation-structure">Add Grade Calculation Structure</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./level-details?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="add-grade-calculation-structure">Add Grade Calculation Structure</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="text-14 sub-topic">Grade Scales</span>
                                                            <div class="d-flex align-items-center cursor-pointer" id="add-new-grade-scale">
                                                                <iconify-icon id="sort-icon" icon="gridicons:add-outline" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                <span class="text-14 dark-theme cursor-pointer px-2 pe-0">Add New Grade Scale</span>
                                                            </div>
                                                        </div>
                                                        <div id="grade-scales-container">

                                                        </div>

                                                        <span class="text-14 sub-topic">Grade Type</span>
                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Select Grade Type</label>
                                                            <select name="grade-type" class="form-select custom-select mt-2" id="grade-type" onchange="gradeTypeChanged(this)">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $grade_types = Database::search("SELECT * FROM `grading_type`");
                                                                if ($grade_types->num_rows > 0) {
                                                                    while ($grade_type = $grade_types->fetch_assoc()) {
                                                                ?>
                                                                        <option value="<?= $grade_type['grading_type_id'] ?>"><?= $grade_type['grading_type'] ?></option>
                                                                <?php
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="grade-type-feedback"></span>
                                                        </div>

                                                        <div class="mt-4 d-none" id="category-grade-type">
                                                            <div class="col-12">
                                                                <div class="row">
                                                                    <?php
                                                                    $grading_category = Database::search("SELECT * FROM `grading_category_type`");
                                                                    while ($data = $grading_category->fetch_assoc()) {
                                                                    ?>
                                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                                            <label for="" class="text-14 main-text required"><?= $data['grading_category_type'] ?> Weight</label>
                                                                            <input type="number" class="form-control custom-input mt-2" placeholder="e.g. 20" id="grade-category-<?= $data['grading_category_type_id'] ?>">
                                                                            <span class="text-12 text-dark-red" id="grade-category-<?= $data['grading_category_type_id'] ?>-feedback"></span>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mt-4 d-none" id="rubrics-grade-type">
                                                            <div class="col-12">
                                                                <div class="row">
                                                                    <div class="d-flex justify-content-end">
                                                                        <div class="d-flex align-items-center cursor-pointer" id="add-new-rubric" onclick="addNewRubric();">
                                                                            <iconify-icon id="sort-icon" icon="gridicons:add-outline" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                            <span class="text-14 dark-theme cursor-pointer px-2 pe-0">Add New Rubric</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-4" id="rubrics-container">

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column mt-4">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="addGradeCalculationStructure(this,'<?php echo $id; ?>');">Add Grade Calculation Structure</button>
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
                            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/fileChooser.js"></script>
                            <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>