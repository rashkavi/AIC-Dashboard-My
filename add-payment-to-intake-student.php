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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./intakes");
        } else {

            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id))) {
                    header("Location: ./intake-details?intake-id=" . $id);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Payment To Intake Student - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
                        <ul class="notifications"></ul>
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
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="accordionParent">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Payments</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'><?= $intakeData["faculty"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programe-id=<?= $intakeData['programme_id'] ?>">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $intakeData['intake_id'] ?>"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Payments</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 m-0">
                                                    <div class="accordion accordion-flush" id="accordionFlushExample">
                                                        <?php
                                                        $paymentsCategory = Database::search("SELECT * FROM `payment_category`");

                                                        if ($paymentsCategory->num_rows > 0) {
                                                            while ($paymentCategory = $paymentsCategory->fetch_assoc()) {
                                                        ?>
                                                                <div class="accordion-item border-0 mb-3">
                                                                    <h2 class="accordion-header border-0">
                                                                        <button class="accordion-button collapsed px-4 py-3 border-0 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse<?= $paymentCategory['payment_category_id'] ?>" aria-expanded="false" aria-controls="flush-collapse<?= $paymentCategory['payment_category_id'] ?>">
                                                                            <iconify-icon icon="bx:category" width="30" height="30" class="dark-theme me-3 bg-light-theme p-4"></iconify-icon>
                                                                            <?= $paymentCategory["payment_category"] ?>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="flush-collapse<?= $paymentCategory['payment_category_id'] ?>" class="accordion-collapse collapse border-0" data-bs-parent="#accordionFlushExample">
                                                                        <div class="accordion-body pt-3 px-0 bg-content-body">
                                                                            <?php
                                                                            $plans = Database::search("SELECT * FROM `payment_plan`");
                                                                            while ($plan = $plans->fetch_assoc()) {
                                                                                $students = Database::search("SELECT COUNT(*) as 'count' FROM `payment_plan_has_students_has_intakes` 
                                                                WHERE `payment_category_payment_category_id`='" . $paymentCategory['payment_category_id'] . "'
                                                                AND `payment_plan_payment_plan_id`='" . $plan['payment_plan_id'] . "'
                                                                AND `students_has_intakes_students_has_intakes_id` IN (SELECT `students_has_intakes_id` FROM `students_has_intakes` WHERE `intakes_intake_id` = '" . $intakeData['intake_id'] . "')");

                                                                                $studentsCount = $students->fetch_assoc();
                                                                            ?>
                                                                                <div class="col-12 px-4 py-3 component-theme mb-10">
                                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                                        <div class="col-12 col-md-10 p-0 g-0 align-content-center">
                                                                                            <div class="row g-0 align-items-center">
                                                                                                <span class="text-15 text-dark-color fw-semibold lh-lg"><?= $plan['payment_plan'] ?></span>
                                                                                            </div>
                                                                                            <div class="row g-0 align-items-center">
                                                                                                <span class="text-14 main-text lh-lg">Plan selected students count : <?= $studentsCount['count'] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                                                            <div class="row gx-3 h-100 align-content-center">
                                                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                                    <button class="custom-btn w-100" onclick="window.location.href='add-payment-to-selected-payment-plan-students?intake-id=<?= $intakeData['intake_id']; ?>&category-id=<?= $paymentCategory['payment_category_id']; ?>&plan-id=<?= $plan['payment_plan_id']; ?>'">Add Payment</button>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
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
                                                        }
                                                        ?>
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
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
} ?>