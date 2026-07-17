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

            $subject = Database::search("
                SELECT 
                    `subjects`.`subject_id`,
                    `faculties`.`faculty`,
                    `faculties`.`faculty_id`,
                    `programmes`.`programme`,
                    `programmes`.`programme_id`,
                    `intakes`.`intake`,
                    `intakes`.`intake_id`,
                    `levels`.`level`,
                    `levels`.`level_id`,
                    `subjects`.`subject`,
                    `subjects`.`grading_type_grading_type_id`
                FROM `subjects`
                INNER JOIN `levels` 
                    ON `subjects`.`levels_level_id` = `levels`.`level_id`
                INNER JOIN `intakes` 
                    ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                INNER JOIN `programmes` 
                    ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                INNER JOIN `faculties` 
                    ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                WHERE `subjects`.`subject_id` = '" . $id . "'
            ");

            if ($subject->num_rows == 0) {
                header("Location: ./dashboard");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject', 29, $id))) {
                    header("Location: ./subject-details?subject-id=" . $id);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Grade Calculation Structure - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
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
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8 col-lg-9">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="grade-calculation">Grade Calculation Structure</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $subjectData['faculty_id'] ?>"><?= $subjectData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $subjectData['level_id'] ?>"><?= $subjectData['level'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $subjectData['subject_id'] ?>"><?= $subjectData['subject'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Grade Calculation Structure</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        if (empty($subjectData['grading_type_grading_type_id'])) {
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject', 29, $id,)) {
                                                        ?>
                                                                <div class="col-12 col-md-4 col-lg-3 mt-3 mt-lg-0">
                                                                    <div class="row gx-3">
                                                                        <div class="col-12 m-md-0 d-flex justify-content-end align-items-center">
                                                                            <button class="custom-btn w-100" onclick="window.location.href='add-grade-calculation-structure?subject-id=<?= $id ?>'">
                                                                                Add Grade Calculation Structure
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                        } else {
                                                            ?>
                                                            <div class="col-12 col-md-4 col-lg-3 mt-3 mt-lg-0">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" onclick="window.location.href='edit-grade-calculation-structure?subject-id=<?= $id ?>'">
                                                                            Edit Grade Calculation Structure
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php
                                                if (!empty($subjectData['grading_type_grading_type_id'])) {
                                                    $gradingType = Database::search("SELECT * FROM `grading_type` WHERE `grading_type_id` = '" . $subjectData['grading_type_grading_type_id'] . "'");
                                                    $gradingType = $gradingType->fetch_assoc();
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme">
                                                        <div class="row gy-3 align-content-center justify-content-center">
                                                            <span class="text-16 sub-topic lh-base"><?= $gradingType['grading_type'] ?> Grading Structure</span>
                                                            <?php
                                                            if ($gradingType['grading_type_id'] == 2) {
                                                            ?>
                                                                <div class="accordion" id="accordionExample">
                                                                    <?php
                                                                    $categories = Database::search("SELECT * FROM `grading_category`
                                                                    INNER JOIN `grading_category_type` ON `grading_category`.`grading_category_type_grading_category_type_id` = `grading_category_type`.`grading_category_type_id` 
                                                                    WHERE `subjects_subject_id` = '" . $id . "'");
                                                                    for ($i = 0; $i < $categories->num_rows; $i++) {
                                                                        $category = $categories->fetch_assoc();
                                                                    ?>
                                                                        <div class="accordion-item br-0">
                                                                            <h2 class="accordion-header br-0">
                                                                                <button class="accordion-button <?= $i == 0 ? '' : 'collapsed' ?> br-0 " type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="<?= $i == 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $i ?>">
                                                                                    <div class="w-100 d-flex justify-content-between align-items-center pe-4">
                                                                                        <span>Category - <?= $i + 1 ?> : <?= $category['grading_category_type'] ?></span>
                                                                                        <span class="p-2 br-5 bg-light-theme"><?= number_format($category['weight'], 0) ?>%</span>
                                                                                    </div>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i == 0 ? 'show' : '' ?>" data-bs-parent="#accordionExample">
                                                                                <div class="accordion-body">
                                                                                    <?php
                                                                                    if ($category['grading_category_type_id'] == 1) {
                                                                                    ?>
                                                                                        <div class="table-responsive-wrapper">
                                                                                            <div class="table-responsive">
                                                                                                <table class="table bl-solid m-0 p-0 g-0">
                                                                                                    <thead>
                                                                                                        <tr class="br-t-5 align-content-center">
                                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                                                    <span class="text-14 main-text fw-medium mx-2">Assignment Code</span>
                                                                                                                </div>
                                                                                                            </th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Assignment</th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Weight</th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Assignment Status</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody id="user-table-data">
                                                                                                        <?php
                                                                                                        $query = "SELECT * FROM `assignments`
                                                                                                        INNER JOIN `assignment_status` ON `assignments`.`assignment_status_assignment_status_id` = `assignment_status`.`assignment_status_id`
                                                                                                        WHERE `subjects_subject_id` = '" . $id . "'";

                                                                                                        $results = Database::search($query);
                                                                                                        if ($results->num_rows > 0) {
                                                                                                            while ($data = $results->fetch_assoc()) {
                                                                                                        ?>
                                                                                                                <tr>
                                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                            <span class="text-14 main-text mx-2"><?= $data["assignment_code"] ?></span>
                                                                                                                        </div>
                                                                                                                    </td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["assignment"]; ?></span></td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["weight"] == null ? 'N/A' : $data["weight"]; ?></span></td>
                                                                                                                    <td class="py-2 align-middle">
                                                                                                                        <?php
                                                                                                                        $status_classes = [
                                                                                                                            "1"  => "table-btn-yellow",
                                                                                                                            "2"  => "table-btn-primary",
                                                                                                                            "3"  => "table-btn-danger",
                                                                                                                            "4"  => "table-btn-orange",
                                                                                                                            "5"  => "table-btn-purple",
                                                                                                                            "6"  => "table-btn-success",
                                                                                                                            "7"  => "table-btn-pink"
                                                                                                                        ];
                                                                                                                        $status = "Not Scheduled";
                                                                                                                        $statusId = '1';
                                                                                                                        if ($data["assignment_status_assignment_status_id"] == 2) {
                                                                                                                            $timezone = new DateTimeZone("Asia/Colombo");
                                                                                                                            $today = new DateTime("now", $timezone);
                                                                                                                            $todayDate = $today->format('Y-m-d H:i:s');

                                                                                                                            $status = "Scheduled";
                                                                                                                            $statusId = '2';

                                                                                                                            $startDateObj = new DateTime($data["start_date"], $timezone);
                                                                                                                            $endDateObj = new DateTime($data["end_date"], $timezone);

                                                                                                                            if ($today >= $startDateObj && $today <= $endDateObj) {
                                                                                                                                $status = "Ongoing";
                                                                                                                                $statusId = '6';
                                                                                                                            } else if ($today > $endDateObj) {
                                                                                                                                $status = "Pending Marking";
                                                                                                                                $statusId = '7';
                                                                                                                            }
                                                                                                                        } else {
                                                                                                                            $status = $data["assignment_status"];
                                                                                                                            $statusId = $data["assignment_status_assignment_status_id"];
                                                                                                                        }
                                                                                                                        $sbutton_class = $status_classes[$statusId];
                                                                                                                        ?>
                                                                                                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $status; ?></button>
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            <?php
                                                                                                            }
                                                                                                        } else {
                                                                                                            ?>
                                                                                                            <tr class="justify-content-center align-middle">
                                                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    if ($category['grading_category_type_id'] == 2) {
                                                                                    ?>
                                                                                        <div class="table-responsive-wrapper">
                                                                                            <div class="table-responsive">
                                                                                                <table class="table bl-solid m-0 p-0 g-0">
                                                                                                    <thead>
                                                                                                        <tr class="br-t-5 align-content-center">
                                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                                                    <span class="text-14 main-text fw-medium mx-2">Exam Name</span>
                                                                                                                </div>
                                                                                                            </th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Exam Type</th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Weight</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody id="user-table-data">
                                                                                                        <?php
                                                                                                        $query = "SELECT * FROM `exams`
                                                                                                        INNER JOIN `exam_types` ON `exams`.`exam_types_exam_type_id` = `exam_types`.`exam_type_id`
                                                                                                        WHERE `subjects_subject_id` = '" . $id . "' AND `exam_types_exam_type_id` != '3'";

                                                                                                        $results = Database::search($query);
                                                                                                        if ($results->num_rows > 0) {
                                                                                                            while ($data = $results->fetch_assoc()) {
                                                                                                        ?>
                                                                                                                <tr>
                                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                            <span class="text-14 main-text mx-2"><?= $data["exam_name"] ?></span>
                                                                                                                        </div>
                                                                                                                    </td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["exam_type"]; ?></span></td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["weight"] == null ? 'N/A' : $data["weight"]; ?></span></td>

                                                                                                                </tr>
                                                                                                            <?php
                                                                                                            }
                                                                                                        } else {
                                                                                                            ?>
                                                                                                            <tr class="justify-content-center align-middle">
                                                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php
                                                                                    }

                                                                                    if ($category['grading_category_type_id'] == 3) {
                                                                                    ?>
                                                                                        <div class="table-responsive-wrapper">
                                                                                            <div class="table-responsive">
                                                                                                <table class="table bl-solid m-0 p-0 g-0">
                                                                                                    <thead>
                                                                                                        <tr class="br-t-5 align-content-center">
                                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                                                                                    <span class="text-14 main-text fw-medium mx-2">Quiz</span>
                                                                                                                </div>
                                                                                                            </th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Quiz Type</th>
                                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Weight</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody id="user-table-data">
                                                                                                        <?php
                                                                                                        $query = "SELECT * FROM `exams`
                                                                                                        INNER JOIN `exam_types` ON `exams`.`exam_types_exam_type_id` = `exam_types`.`exam_type_id`
                                                                                                        INNER JOIN `quiz` ON `exams`.`exam_id` = `quiz`.`exams_exam_id`
                                                                                                        INNER JOIN `quiz_type` ON `quiz`.`quiz_type_quiz_type_id` = `quiz_type`.`quiz_type_id`
                                                                                                        WHERE `subjects_subject_id` = '" . $id . "' AND `exam_types_exam_type_id` = '3'";

                                                                                                        $results = Database::search($query);
                                                                                                        if ($results->num_rows > 0) {
                                                                                                            while ($data = $results->fetch_assoc()) {
                                                                                                        ?>
                                                                                                                <tr>
                                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                            <span class="text-14 main-text mx-2"><?= $data["exam_name"] ?></span>
                                                                                                                        </div>
                                                                                                                    </td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["quiz_type"]; ?></span></td>
                                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["weight"] == null ? 'N/A' : $data["weight"]; ?></span></td>
                                                                                                                </tr>
                                                                                                            <?php
                                                                                                            }
                                                                                                        } else {
                                                                                                            ?>
                                                                                                            <tr class="justify-content-center align-middle">
                                                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        <?php
                                                                                                        }
                                                                                                        ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            <?php
                                                            }
                                                            if ($gradingType['grading_type_id'] == 4) {
                                                            ?>
                                                                <div class="accordion" id="accordionExample">
                                                                    <?php
                                                                    $rubrics = Database::search("SELECT * FROM `rubrics` WHERE `subjects_subject_id` = '" . $id . "'");
                                                                    for ($i = 0; $i < $rubrics->num_rows; $i++) {
                                                                        $rubric = $rubrics->fetch_assoc();
                                                                    ?>
                                                                        <div class="accordion-item br-0">
                                                                            <h2 class="accordion-header br-0">
                                                                                <button class="accordion-button <?= $i == 0 ? '' : 'collapsed' ?> br-0 " type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>" aria-expanded="<?= $i == 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $i ?>">
                                                                                    <div class="w-100 d-flex justify-content-between align-items-center pe-4">
                                                                                        <span>Rubric - <?= $i + 1 ?> : <?= $rubric['rubric_name'] ?></span>
                                                                                        <span class="p-2 br-5 bg-light-theme"><?= number_format($rubric['weight'], 0) ?>%</span>
                                                                                    </div>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i == 0 ? 'show' : '' ?>" data-bs-parent="#accordionExample">
                                                                                <div class="accordion-body">
                                                                                    <div class="table-responsive-wrapper">
                                                                                        <div class="table-responsive" id="rubric-criteria-table-container">
                                                                                            <table class="table bl-solid m-0 p-0 g-0" id="rubric-criteria-table">
                                                                                                <thead>
                                                                                                    <tr class="br-t-5 align-content-center">
                                                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                                                <span class="text-14 main-text fw-medium mx-2">Criteria Name</span>
                                                                                                            </div>
                                                                                                        </th>
                                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Max Score</th>
                                                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Weight</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody id="user-table-data">
                                                                                                    <?php
                                                                                                    $query = "SELECT * FROM `rubric_criteria`
                                                                                                            WHERE `rubrics_rubric_id` = '" . $rubric['rubric_id'] . "'
                                                                                                           ";

                                                                                                    $results = Database::search($query);
                                                                                                    if ($results->num_rows > 0) {
                                                                                                        while ($data = $results->fetch_assoc()) {
                                                                                                    ?>
                                                                                                            <tr>
                                                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                        <span class="text-14 main-text mx-2"><?= $data["criteria_name"] ?></span>
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["max_score"]; ?></span></td>
                                                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["weight"]; ?></span></td>
                                                                                                            </tr>
                                                                                                        <?php
                                                                                                        }
                                                                                                    } else {
                                                                                                        ?>
                                                                                                        <tr class="justify-content-center align-middle">
                                                                                                            <td colspan="13" class="align-middle justify-content-center">
                                                                                                                <div class="d-flex flex-column align-items-center">
                                                                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                                </div>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }

                                                                    ?>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php
                                                } else {
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme">
                                                        <div class="row gy-3 align-content-center justify-content-center">
                                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
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