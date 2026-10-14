<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $branch = isset($decoded['branch']) ? $decoded['branch'] : 0;
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                    $programme = isset($decoded['programme']) ? $decoded['programme'] : 0;
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : 0;
                    $user = isset($decoded['user']) ? $decoded['user'] : 0;
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "
                        SELECT
                            `students`.`student_id`  AS `sId`,
                             `students_has_intakes`.`students_has_intakes_id` AS `shiId`,
                            `login_details`.`first_name`,
                            `login_details`.`last_name`,
                            `login_details`.`email`,
                            `students`.`mobile`,
                            `login_details`.`whatsapp`,
                            `students`.`profile_img`,
                            `students_has_intakes`.`addmission_date` AS`date_registered`,
                            `title`.`title`,

                            `lead_student_status`.`lead_student_status`,
                            `lead_student_status`.`lead_student_status_id`,

                            `login_details`.`verification`,
                            `login_details`.`reset_time`,

                            `users`.`user_fname`,
                            `users`.`user_lname`,

                            `title2`.`title`                                           AS `user_title`,

                            `students_logs`.`students_log_id`,
                            `students_logs`.`date_updated`,
                            `students_logs`.`lead_student_status_lead_student_status_id` AS `latest_log_status_id`,

                            `programmes`.`programme`,
                            `students_has_intakes`.`students_has_intakes_id`,

                            CASE
                                WHEN `payment`.`students_has_intakes_students_has_intakes_id` IS NOT NULL
                                    THEN 1
                                ELSE 0
                            END AS `has_due_payment`

                        FROM `students`

                        INNER JOIN `login_details`
                            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`

                        INNER JOIN `title`
                            ON `students`.`title_title_id` = `title`.`title_id`
                        
                        INNER JOIN `students_has_intakes`
                            ON `students`.`student_id` = `students_has_intakes`.`students_student_id`

                        INNER JOIN `lead_student_status`
                            ON `students_has_intakes`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`

                        INNER JOIN `intakes`
                            ON `intakes`.`intake_id` = `students_has_intakes`.`intakes_intake_id`

                        INNER JOIN `programmes`
                            ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`

                        INNER JOIN `faculties`
                            ON `faculties`.`faculty_id` = `programmes`.`faculties_faculty_id`

                        INNER JOIN `users`
                            ON `students_has_intakes`.`counselor_user_id` = `users`.`user_id`

                        INNER JOIN `title` AS `title2`
                            ON `users`.`title_title_id` = `title2`.`title_id`

                        LEFT JOIN `students_logs`
                            ON `students_logs`.`students_log_id` = (
                                SELECT `sl2`.`students_log_id`
                                FROM `students_logs` `sl2`
                                WHERE `sl2`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                ORDER BY `sl2`.`date_updated` DESC, `sl2`.`students_log_id` DESC
                                LIMIT 1
                            )

                        LEFT JOIN (
                            SELECT DISTINCT `pshi`.`students_has_intakes_students_has_intakes_id`
                            FROM `course_payments` AS `cp`
                            INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                            WHERE `cp`.`payment_status_payment_status_id` = '1'
                            AND `cp`.`due_date` < '" . date("Y-m-d") . "'
                        ) AS `payment`
                            ON `payment`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                        WHERE 1=1
                    ";

                    if ($search_text != "") {
                        $query .= " AND (`students`.`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($sort != 0) {
                        $query .= " AND `lead_student_status`.`lead_student_status_id` = '" . $sort . "'";
                    }

                    if ($intake != 0) {
                        $query .= " AND `intake_id`='" . $intake . "' ";
                    } else if ($programme != 0) {
                        $query .= " AND `programme_id`='" . $programme . "' ";
                    } else if ($faculty != 0) {
                        $query .= " AND `faculty_id`='" . $faculty . "' ";
                    } else if ($branch != 0) {
                        $query .= " AND `faculties`.`branches_branch_id`='" . $branch . "' ";
                    }

                    if ($paymentStatus == 1) {
                        $query .= " AND `payment`.`students_has_intakes_students_has_intakes_id` IS NOT NULL";
                    } else if ($paymentStatus == 2) {
                        $query .= " AND `payment`.`students_has_intakes_students_has_intakes_id` IS NULL";
                    }

                    if ($user != 0) {
                        $query .= " AND `users`.`user_id`='" . $user . "'";
                    }

                    if ($sessionUserRole == "Counselor") {
                        $query .= " AND `users`.`user_id`='" . $sessionUserObject['user_id'] . "'";
                    } else if ($sessionUserRole == "Branch Manager") {
                        $userData = Database::search("SELECT * FROM `users` WHERE `user_id`='" . $sessionUserObject['user_id'] . "'")->fetch_assoc();
                        $query .= " AND (`users`.`user_id`='" . $sessionUserObject['user_id'] . "' 
                                                                OR `faculties`.`branches_branch_id`='" . $userData['branches_branch_id'] . "')
                                                                ";
                    }

                    $order_key = " ORDER BY";

                    if ($sortByName != 0) {
                        $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                        $query .= " " . $order_key . " `first_name` " . $sort;
                        $order_key = ", ";
                    }

                    if ($sortByDate != 0) {
                        $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                        $query .= " " . $order_key . " `students`.`student_id` " . $sort;
                    }

                    if ($sortByName == 0 && $sortByDate == 0) {
                        $query .= " " . $order_key . "  `students_logs`.`date_updated` DESC,
                                        `students_has_intakes`.`students_has_intakes_id` DESC";
                    }

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleInquiryPagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        </div>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Programme</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Date Added</span>
                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Assigned Counselor</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>


                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>

                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($students->num_rows > 0) {
                                while ($data = $students->fetch_assoc()) {
                            ?>
                                    <tr class="position-relative">
                                        <td class="align-middle lead-id-td">
                                            <?php
                                            if ($data['has_due_payment']) {
                                            ?>
                                                <div class="lead-id-card position-relative d-flex align-items-center">
                                                    <span class="mini-left-ribbon"
                                                        style="background: #EB5757">
                                                        Due
                                                    </span>
                                                    <!-- <div class="lead-status-icon-wrap lead-icon-flame"
                                                        style="background: #FFE1E1; color: #C0392B;">
                                                        <iconify-icon icon="mdi:fire" width="18" height="18"></iconify-icon>
                                                    </div> -->
                                                    <div class="d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                        <?php
                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                        ?>
                                                            <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                        <?php
                                                        } else {
                                                            $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                            $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                        ?>
                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                    </div>

                                                </div>
                                            <?php
                                            } else {
                                            ?>
                                                <div class="d-flex align-items-center cursor-pointer px-4" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                </div>
                                            <?php
                                            }
                                            ?>

                                        </td>

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
                                        <?php

                                        $displayStatusId = $data['lead_student_status_id'];
                                        $displayStatus = $data['lead_student_status'];

                                        // if ($data['lead_student_status_id'] == 5) {

                                        //     $requiredDocumentTypes = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

                                        //     $documentCount = Database::search("
                                        //     SELECT COUNT(DISTINCT students_identity_documents_type_id) AS total
                                        //     FROM students_identity_documents
                                        //     WHERE students_student_id = '" . $data['sId'] . "'
                                        //     AND students_identity_documents_type_id IN (1,2,3,4,5,6,7,8,9,10,11)
                                        // ");

                                        //     $documentData = $documentCount->fetch_assoc();

                                        //     if ((int)$documentData['total'] < count($requiredDocumentTypes)) {

                                        //         // Additional Documents Pending
                                        //         $displayStatusId = 6;
                                        //         $displayStatus = "Additional Documents Pending";
                                        //     } else {
                                        //         // First Installment Payment Pending
                                        //         $displayStatusId = 7;
                                        //         $displayStatus = "First Installment Payment Pending";
                                        //     }
                                        // }
                                        ?>

                                        <td class="py-2 align-middle">
                                            <button class="table-btn label-student-status-<?php echo $displayStatusId; ?> w-100">
                                                <?php echo htmlspecialchars($displayStatus); ?>
                                            </button>
                                        </td>

                                        <td class="py-3 align-middle">
                                            <div class="dropdown">
                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                    <?php
                                                    if ($displayStatusId == 17 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicDepartment($sessionUserRole) || SessionUser::ifUserIsPro($sessionUserRole))) {
                                                    ?>
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendWelcomeEmail('<?php echo $data['shiId']; ?>')">
                                                                <iconify-icon icon="bi:send" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Resend Welcome Email</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <?php
                                                    }
                                                    ?>
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="registration-view?registration-id=<?php echo $data['shiId']; ?>">
                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            View</a></li>
                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="registration-update-history?registration-id=<?php echo $data['shiId']; ?>">
                                                            <iconify-icon icon="ic:baseline-history" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            Update History</a></li>
                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <?php
                                                    if ($displayStatusId != 17) {
                                                    ?>
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-registration?registration-id=<?php echo $data['shiId']; ?>">
                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Edit</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <?php
                                                    }
                                                    if ($displayStatusId == 2 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                    ?>
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="re-upload-registration-payment-slip?inquiry-id=<?php echo $data['shiId']; ?>">
                                                                <iconify-icon icon="material-symbols-light:upload-2-rounded" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Re-Upload Registration Payment Slip</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                    <?php
                                                    }
                                                    if ($displayStatusId >= 5) {
                                                    ?>

                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="upload-other-documents?registration-id=<?php echo $data['shiId']; ?>">
                                                                <iconify-icon icon="solar:document-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Other Documents</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?php echo $data['students_has_intakes_id']; ?>&path=inquiries">
                                                                <iconify-icon icon="tabler:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Payment Plan</a></li>
                                                    <?php
                                                    }

                                                    if ($displayStatusId == 5 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openRegistrationVerificationModal(<?= $data['shiId']; ?>)">
                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                    width="20"
                                                                    height="20"
                                                                    class="sub-text me-3"></iconify-icon>
                                                                Confirm Academic & Initial Payment Completion
                                                            </a>
                                                        </li>
                                                    <?php
                                                    }
                                                    if ($displayStatusId == 12 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole))) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openRegistrationVerificationModal(<?= $data['shiId']; ?>)">
                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                    width="20"
                                                                    height="20"
                                                                    class="sub-text me-3"></iconify-icon>
                                                                Confirm Academic & Initial Payment Completion
                                                            </a>
                                                        </li>
                                                    <?php
                                                    }

                                                    if ($displayStatusId == 11 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicAdministration($sessionUserRole))) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openAcademicAdministrationRejectModal(<?= $data['shiId']; ?>)">
                                                                <iconify-icon icon="solar:close-circle-bold"
                                                                    width="20"
                                                                    height="20"
                                                                    class="sub-text me-3"></iconify-icon>
                                                                Academic Documents Rejected
                                                            </a>
                                                        </li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openAcademicAdministrationConfirmModal(<?= $data['shiId']; ?>,'<?= $data['ms_team_username'] ?>')">
                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                    width="20"
                                                                    height="20"
                                                                    class="sub-text me-3"></iconify-icon>
                                                                Confirm Academic Completion
                                                            </a>
                                                        </li>
                                                    <?php
                                                    }
                                                    if ($displayStatusId == 14 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcdemicDepartment($sessionUserRole))) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openAcademicDepartmentConfirmModal(<?= $data['shiId']; ?>)">
                                                                <iconify-icon icon="solar:verified-check-bold"
                                                                    width="20"
                                                                    height="20"
                                                                    class="sub-text me-3"></iconify-icon>
                                                                Confirm Enrollement Completion
                                                            </a>
                                                        </li>
                                                    <?php
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiryPagination('previousPage');">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleInquiryPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                renderPageButton($i, $pageno == $i);
                                            } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                $shudderPages1 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                $shudderPages2 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            }
                                        }
                                        ?>

                                    </ul>
                                </div>
                                <!-- Next Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiryPagination('nextPage',<?= $number_of_pages ?>);">
                                    <span class="text-14 main-text fw-semibold">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                </button>
                            </div>
                        </nav>
                    </div>
<?php
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
