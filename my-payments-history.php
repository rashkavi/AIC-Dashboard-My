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
        if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
            header("Location: ./dashboard");
        } else {

            $query = "SELECT 
                                                                        lpp.lecturers_paid_payment_id,
                                                                        lpp.amount,
                                                                        lpp.date_paid,
                                                                        lecturer.user_fname AS lecturer_fname,
                                                                        lecturer.user_lname AS lecturer_lname,
                                                                        lecturer.profile_img AS lecturer_profile_img,
                                                                        lecturer_title.title AS lecturer_title,
                                                                        paid_by.user_fname AS paid_by_fname,
                                                                        paid_by.user_lname AS paid_by_lname,
                                                                        paid_by.profile_img AS paid_by_profile_img,
                                                                        paid_by_title.title AS paid_by_title
                                                                         FROM `lecturers_paid_payments` AS lpp
                                                                        INNER JOIN `users` AS `lecturer` ON `lpp`.`users_user_id` = `lecturer`.`user_id`
                                                                        INNER JOIN `title` AS `lecturer_title` ON `lecturer`.`title_title_id` = `lecturer_title`.`title_id`
                                                                        INNER JOIN `users` AS `paid_by` ON `lpp`.`paid_by` = `paid_by`.`user_id`
                                                                        INNER JOIN `title` AS `paid_by_title` ON `paid_by`.`title_title_id` = `paid_by_title`.`title_id`
                                                                        WHERE lpp.`users_user_id` = '" . $sessionUserObject['user_id'] . "'
                                                                        ORDER BY lpp.`date_paid` DESC
                                                                ";

            $results = Database::search($query);
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>My Payments History - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            include "./includes/side-menu.php";
                            load_side_menu::load("Payments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Payments");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">My Payments History</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./my-payments">My Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./my-payments-history">Payment History</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">

                                                                <span class="text-14 dark-theme fw-medium"><?= $results->num_rows; ?> Payments</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="users-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-14 main-text fw-medium">Lecturer Name</span>
                                                                                </div>
                                                                            </th>

                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid Amount</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Paid By</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">
                                                                                <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $payments = $results;

                                                                        if ($payments->num_rows > 0) {
                                                                            while ($data = $payments->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                <?php
                                                                                                if (!empty($data["lecturer_profile_img"]) && file_exists('./assets/imgs/users/' . $data["lecturer_profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/users/<?= $data["lecturer_profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                <?php
                                                                                                } else {
                                                                                                    $firstNameInitial = strtoupper(substr($data["lecturer_fname"], 0, 1));
                                                                                                    $lastNameInitial = strtoupper(substr($data["lecturer_lname"], 0, 1));
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["lecturer_title"] . "." . $data["lecturer_fname"] . " " . $data["lecturer_lname"]; ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($data["amount"]); ?></span></td>

                                                                                    <td class="py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <?php
                                                                                            if (!empty($data["paid_by_profile_img"]) && file_exists('./assets/imgs/users/' . $data["paid_by_profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?= $data["paid_by_profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["paid_by_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["paid_by_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?= $data["paid_by_title"] . "." . $data["paid_by_fname"] . " " . $data["paid_by_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d", strtotime($data["date_paid"])); ?></span></td>

                                                                                    <td class="pe-4 py-3 align-middle">
                                                                                        <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='lecturer-payment-details?payment-id=<?= $data['lecturers_paid_payment_id'] ?>'">View</button>
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

                <!-- Confirmation Modal=>Start -->
                <div class="modal fade  confirmation " id="confirmation" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Confirmation Message</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="model-body p-0 mt-3">
                                <div class="col-12 container-fluid p-0">
                                    <div class="d-flex flex-row align-items-center">
                                        <iconify-icon icon="fluent:question-circle-16-filled" class="dark-blue" width="40" height="40"></iconify-icon>
                                        <p class="text-16 main-text px-2 m-0">Would you like to remove selected
                                            examination ?</p>
                                    </div>
                                    <div class="col-12 d-flex flex-row align-items-center justify-content-end mt-3">
                                        <button class="custom-btn px-5 pe-5 me-3">Yes</button>
                                        <button class="custom-btn component-theme bl-solid main-text px-5 pe-5">No</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Confirmation Modal => End -->

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>