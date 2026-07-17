<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/SlipVerifier.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id']))) {
            header("Location: ./students");
        } else {
            $leadId = (int) $_GET["inquiry-id"];

            $lead = Database::search("
    SELECT 
                                                             `students`.`student_id` AS 'sId', 
                                                         
                                                             `login_details`.`first_name`, 
                                                             `login_details`.`last_name`, 
                                                             `login_details`.`email`, 
                                                         
                                                             `students`.`mobile`, 
                                                         
                                                             `login_details`.`whatsapp`, 
                                                             `students`.`profile_img`, 
                                                             `login_details`.`date_registered`, 
                                                         
                                                             `title`.`title`, 
                                                         
                                                             `lead_student_status`.`lead_student_status`,
                                                             `lead_student_status`.`lead_student_status_id`,
                                                         
                                                             `login_details`.`verification`,
                                                             `login_details`.`reset_time`,
                                                         
                                                             `users`.`user_fname`,
                                                             `users`.`user_lname`,
                                                         
                                                             `title2`.`title` AS `user_title`,
                                                         
                                                             `sl`.`students_log_id`,
                                                             `sl`.`date_updated` AS `latest_log_date`,
                                                             `sl`.`log` AS `latest_log`,
                                                             `sl`.`note` AS `latest_note`,
                                                             `sl`.`lead_student_status_lead_student_status_id` AS `latest_log_status_id`,
                                                         
                                                             `updated_user`.`user_id` AS `updated_user_id`,
                                                             `updated_user`.`user_fname` AS `updated_user_fname`,
                                                             `updated_user`.`user_lname` AS `updated_user_lname`,
                                                         
                                                             `updated_user_title`.`title` AS `updated_user_title`
                                                         
                                                         FROM `students`
                                                         
                                                         INNER JOIN `login_details`
                                                             ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                         
                                                         INNER JOIN `title`
                                                             ON `students`.`title_title_id` = `title`.`title_id`
                                                         
                                                         INNER JOIN `lead_student_status`
                                                             ON `students`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                         
                                                         INNER JOIN `users`
                                                             ON `students`.`users_user_id` = `users`.`user_id`
                                                         
                                                         INNER JOIN `title` AS `title2`
                                                             ON `users`.`title_title_id` = `title2`.`title_id`
                                                         
                                                         LEFT JOIN `students_logs` AS `sl`
                                                             ON `sl`.`students_log_id` = (
                                                                 SELECT `sl2`.`students_log_id`
                                                                 FROM `students_logs` AS `sl2`
                                                                 WHERE `sl2`.`students_student_id` = `students`.`student_id`
                                                                   AND `sl2`.`lead_student_status_lead_student_status_id` IN (1,2,3,4)
                                                                 ORDER BY `sl2`.`date_updated` DESC
                                                                 LIMIT 1
                                                             )
                                                         
                                                         LEFT JOIN `users` AS `updated_user`
                                                             ON `sl`.`users_user_id` = `updated_user`.`user_id`
                                                         
                                                         LEFT JOIN `title` AS `updated_user_title`
                                                             ON `updated_user`.`title_title_id` = `updated_user_title`.`title_id`
                                                         WHERE `students`.`student_id` = '" . $leadId . "'
                                                            GROUP BY `students`.`student_id`
                                                         ORDER BY `students`.`student_id` DESC
");

            if ($lead->num_rows == 0) {
                header("Location: ./inquiries");
                exit;
            }

            $lead = $lead->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Inquiries Registration Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
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

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-xxl-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Inquiry Registration Payment</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./inquiries-registration-payments">Inquiry Registration Payments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row align-items-center justify-content-end gx-3 h-100">
                                                        <?php
                                                        if (!($lead['latest_log_status_id'] == 4 || $lead['latest_log_status_id'] == 3 || $lead['latest_log_status_id'] == 2)) {
                                                        ?>
                                                            <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                                <button class="custom-danger-btn w-100" type="button" data-bs-target="#updateLead" data-bs-toggle="modal">
                                                                    Reject Payment
                                                                </button>
                                                            </div>

                                                            <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location.href='create-student-payment-plan?inquiry-id=<?= $leadId ?>'">
                                                                    Create Payment Plan
                                                                </button>
                                                            </div>
                                                        <?php
                                                        } else if (!($lead['latest_log_status_id'] == 2)) {
                                                        ?>
                                                            <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location.href='inquiry-payment-plan?inquiry-id=<?= $leadId ?>'">
                                                                    View Payment Plan
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-0 m-0">
                                            <div class="row p-4 m-0 g-0 component-theme w-100">
                                                <div class="col-12">
                                                    <div class="payment-timeline">
                                                        <div class="timeline-progress">
                                                            <?php
                                                            $query = "SELECT * FROM `lead_student_status`";
                                                            $progress = 0;
                                                            $progressColor = "";
                                                            switch ($lead['latest_log_status_id'] == null ? 0 : $lead['latest_log_status_id']) {
                                                                case 0:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 2 * 0;
                                                                    $query .= " WHERE `lead_student_status_id` IN (3,4)";
                                                                    break;
                                                                case 1:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 2 * 0;
                                                                    $query .= " WHERE `lead_student_status_id` IN (1,4)";
                                                                    break;
                                                                case 2:
                                                                    $progressColor = "reject";
                                                                    $progress = 100 / 2 * 1;
                                                                    $query .= " WHERE `lead_student_status_id` IN (2,4)";
                                                                    break;
                                                                case 3:
                                                                    $progressColor = "accepted";
                                                                    $progress = 100 / 2 * 2;
                                                                    $query .= " WHERE `lead_student_status_id` IN (3,4)";
                                                                    break;
                                                                case 4:
                                                                    $progressColor = "accepted";
                                                                    $progress = 100 / 2 * 2;
                                                                    $query .= " WHERE `lead_student_status_id` IN (3,4)";
                                                                    break;
                                                            }

                                                            ?>
                                                            <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                        </div>
                                                        <div class="timeline-items">
                                                            <?php

                                                            $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];

                                                            $paymentStatus = Database::search($query);
                                                            while ($status = $paymentStatus->fetch_assoc()) {
                                                                $timelineItemColor = "";
                                                                $bubbleAction = "";

                                                                if ($lead['latest_log_status_id'] >= $status['lead_student_status_id']) {
                                                                    if ($lead['latest_log_status_id'] == 2) {
                                                                        $timelineItemColor = "reject";
                                                                    } else if ($lead['latest_log_status_id'] == 4 || $lead['latest_log_status_id'] == 3) {
                                                                        $timelineItemColor = "accepted";
                                                                    } else {
                                                                        if (($lead['latest_log_status_id']  ==  $status['lead_student_status_id']) || ($lead['latest_log_status_id'] == null)) {
                                                                            $bubbleAction = "pulse";
                                                                        } else {
                                                                            $timelineItemColor = "completed";
                                                                        }
                                                                    }
                                                                }

                                                            ?>
                                                                <div class="timeline-item <?= $timelineItemColor ?>">
                                                                    <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                        <iconify-icon icon="<?= $icons[$status['lead_student_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                    </div>
                                                                    <div class="timeline-label">
                                                                        <span><?= $status['lead_student_status'] ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>


                                                <!-- Slip Line -->
                                                <?php
                                                $paymnetSlips = Database::search("
                                        SELECT *
                                        FROM students_identity_documents
                                        WHERE students_student_id = '" . $leadId . "'
                                        AND `students_identity_documents_type_id` = '8'
                                        ORDER BY students_identity_documents_type_id ASC, date_added DESC
                                        ");
                                                if ($paymnetSlips->num_rows > 0) {
                                                ?>
                                                    <div class="col-12">
                                                        <div class="row p-4 m-0 g-0 align-content-center bl-solid mt-4">
                                                            <span class="sub-topic lh-lg">Payment Slips</span>
                                                            <div class="accordion accordion-flush" id="accordionParent">
                                                                <?php
                                                                while ($paymentSlip = $paymnetSlips->fetch_assoc()) {
                                                                    $slipVerifier = new SlipVerifier();
                                                                    $fileType = $slipVerifier->checkFileType($paymentSlip['document']);
                                                                    $bankAccNo = '';
                                                                    $fileUrl = "";

                                                                    $fileUrl = Project::getBaseUrl() . "/assets/uploads/student-identity-documents/payment-slips/" . $paymentSlip['document'];

                                                                    $fileData = $slipVerifier->extractSlipDetails($fileUrl);
                                                                    $isExtracted = !empty($fileData['success']);

                                                                    $paidDate = $fileData['paid_date'] ?? null;
                                                                    $paidTime = $fileData['paid_time'] ?? null;
                                                                    $amount = $fileData['amount'] ?? null;
                                                                    $bankAccount = $fileData['bank_account'] ?? null;
                                                                    if ($fileType != 'unsupported') {
                                                                ?>
                                                                        <div class="accordion-item mt-4">
                                                                            <h2 class="accordion-header bl-solid">
                                                                                <button class="accordion-button collapsed d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse-<?= $paymentSlip['students_identity_document_id'] ?>" aria-expanded="false" aria-controls="flush-collapse-<?= $paymentSlip['students_identity_document_id'] ?>">
                                                                                    <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="Payment Slip">
                                                                                    <?php
                                                                                    if ($fileData['verified'] == true) {
                                                                                    ?>
                                                                                        <iconify-icon icon="ph:seal-check-duotone" width="40" height="40" class="text-dark-green"></iconify-icon>
                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                            <span class="text-dark-green text-16 fw-semibold">Payment slip successfully verified</span>
                                                                                            <span class="text-12">Transaction details have been authenticated.</span>
                                                                                        </div>
                                                                                    <?php
                                                                                    } else {
                                                                                    ?>
                                                                                        <iconify-icon icon="ant-design:close-circle-twotone" width="40" height="40" class="text-dark-red"></iconify-icon>
                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                            <span class="text-dark-red text-16 fw-semibold">Unable to verify payment</span>
                                                                                            <span class="text-12">The uploaded slip appears to be invalid, duplicated, or altered.</span>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </button>
                                                                            </h2>
                                                                            <div id="flush-collapse-<?= $paymentSlip['students_identity_document_id'] ?>" class="accordion-collapse collapse bl-solid-lrb" data-bs-parent="#accordionParent">
                                                                                <div class="accordion-body">
                                                                                    <div class="col-12">
                                                                                        <div class="row p-0 m-0">
                                                                                            <hr>
                                                                                            <div class="col-12 col-xl-6">
                                                                                                <div class="row">
                                                                                                    <div class="col-12 mt-">
                                                                                                        <div class="d-flex align-items-start">
                                                                                                            <?php
                                                                                                            if (!empty($paidDate)) {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-green text-16 fw-semibold">Payment Date</span>
                                                                                                                    <span class="text-14">Payment date successfully matched with bank transaction details.</span>
                                                                                                                </div>
                                                                                                            <?php

                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-red text-16 fw-semibold">Payment Date</span>
                                                                                                                    <span class="text-14">Payment date is missing or does not correspond with the system records.</span>
                                                                                                                </div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                    <div class="col-12">
                                                                                                        <div class="d-flex align-items-start">
                                                                                                            <?php
                                                                                                            if (!empty($amount)) {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-green text-16 fw-semibold">Payment Amount</span>
                                                                                                                    <span class="text-14">LKR <?= $amount ?> Payment amount confirmed and accurately reflected in the slip.</span>
                                                                                                                </div>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-red text-16 fw-semibold">Payment Amount</span>
                                                                                                                    <span class="text-14">Mismatch detected in the declared and recorded payment amount.</span>
                                                                                                                </div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                    <div class="col-12">
                                                                                                        <div class="d-flex align-items-start">
                                                                                                            <?php
                                                                                                            if (!empty($bankAccount)) {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-green text-16 fw-semibold">Account Number</span>
                                                                                                                    <span class="text-12">Institution account number is correct and consistent with transaction details.</span>
                                                                                                                </div>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                <div class="ms-3 d-flex flex-column">
                                                                                                                    <span class="text-dark-red text-16 fw-semibold">Account Number</span>
                                                                                                                    <span class="text-12">Incorrect or unrecognized account number used for this transaction.</span>
                                                                                                                </div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                <div class="row justify-content-end align-items-center">
                                                                                                    <?php
                                                                                                    if ($fileData['verified'] == true) {
                                                                                                    ?>
                                                                                                        <div class="alert alert-success br-0" role="alert">
                                                                                                            ✅ This payment slip has been successfully verified and is valid.
                                                                                                        </div>
                                                                                                    <?php
                                                                                                    } else {
                                                                                                    ?>
                                                                                                        <div class="alert alert-warning br-0" role="alert">
                                                                                                            ⚠️ This payment slip has not been verified yet. Please review it carefully before approval.
                                                                                                        </div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                    <button class="custom-btn col-4" type="button" onclick="window.open('<?= $fileUrl; ?>', '_blank');">View</button>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
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
                                                <?php
                                                }

                                                ?>

                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row p-4 m-0 bl-solid">
                                                        <span class="sub-topic p-0">Other Documents</span>

                                                        <?php

                                                        $studentIdentityDocuments = Database::search("SELECT * FROM `students_identity_documents` 
                                                        WHERE `students_student_id` = '" . $lead['sId'] . "' AND `students_identity_documents_type_id` IN (11,12)");
                                                        if ($studentIdentityDocuments->num_rows > 0) {
                                                        ?>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3 p-0">
                                                                    <?php
                                                                    $isHasAnyDocument = false;

                                                                    while ($document = $studentIdentityDocuments->fetch_assoc()) {
                                                                        if (!empty($document['document'])) {
                                                                            $folder = $document['students_identity_documents_type_id'] == 11 ? "discount-approved-documents" : "signed-fee-structure";
                                                                            $documentPath = Project::getBaseUrl() . "/assets/uploads/student-identity-documents/" . $folder . "/" . $document['document'];
                                                                            $headers = @get_headers($documentPath);

                                                                            $hasDocument = false;

                                                                            if ($headers && strpos($headers[0], '200') !== false) {
                                                                                $hasDocument = true;
                                                                            }
                                                                            if ($hasDocument) {
                                                                                $isHasAnyDocument = true;
                                                                    ?>
                                                                                <div class="col-12 col-md-6 my-3 p-0 file-item cursor-pointer">
                                                                                    <div class="p-3 bl-solid">
                                                                                        <div class="row">
                                                                                            <div class="col-2">
                                                                                                <img src="assets/imgs/icons/image.png" alt="">
                                                                                            </div>

                                                                                            <div class="col-8">
                                                                                                <div class="file-name d-flex flex-column">

                                                                                                    <div class="name">
                                                                                                        <?= $document['document'] ?>
                                                                                                    </div>

                                                                                                    <a
                                                                                                        href="<?= $documentPath ?>"
                                                                                                        target="_blank"
                                                                                                        onclick="event.stopPropagation();">
                                                                                                        View
                                                                                                    </a>

                                                                                                </div>
                                                                                            </div>

                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                        <?php
                                                                            }
                                                                        }
                                                                    }

                                                                    if (!$isHasAnyDocument) {
                                                                        ?>
                                                                        <div class="col-12 col-md-4 my-3 file-item cursor-pointer">
                                                                            <div class="p-3 bl-solid">
                                                                                <span>No document uploaded</span>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>

                                                        <?php
                                                        } else {
                                                        ?>
                                                            <div class="col-12 col-md-4 my-3 file-item cursor-pointer">
                                                                <div class="p-3 bl-solid">
                                                                    <span>No document uploaded</span>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>
                                                </div>



                                                <div class="col-12 mt-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `students_logs` 
                                                                                    WHERE `students_student_id`='" . $lead['sId'] . "'
                                                                                    ");
                                                                        $data = $logs_count->fetch_assoc();
                                                                        ?>
                                                                        <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Logs</span>
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
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Updated Status</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php
                                                                                $query = "SELECT * FROM `students_logs`
                                                                                    INNER JOIN `users` ON `students_logs`.`users_user_id` = `users`.`user_id`
                                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                    LEFT JOIN `lead_student_status` ON `students_logs`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                                                    WHERE `students_student_id`='" . $lead['sId'] . "' ORDER BY `date_updated` DESC
                                                                                    ";

                                                                                $users = Database::search($query);
                                                                                if ($users->num_rows > 0) {
                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                        $sbutton_class = "label-role-" . $data["lead_student_status_id"];
                                                                                ?>
                                                                                        <tr>
                                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["user_id"]; ?>" onchange="checkRowEvents(<?= $data['user_id']; ?>);">
                                                                                                    <?php
                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                    ?>
                                                                                                        <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                    <?php
                                                                                                    } else {
                                                                                                        $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                        $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                    ?>
                                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                                </div>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"]; ?></span></td>
                                                                                            <td class="py-2 align-middle">
                                                                                                <label class="table-btn text-center label-student-status-<?= $data["lead_student_status_id"] ?> w-100"> <?php echo $data["lead_student_status"]; ?></label>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
                                                                                            <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
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

                <div class="modal fade" id="updateLead" tabindex="-1" role="dialog" aria-labelledby="updateLeadTitle" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLongTitle">Update Registration Payment</h5>
                            </div>
                            <div class="modal-body">
                                <div class="col-12 p-0 g-0">
                                    <div class="row gx-4 gy-3">
                                        <div class="col-12 d-flex flex-column">
                                            <label class="text-14 main-text required" for="">Payment Status</label>
                                            <select class="form-select custom-select mt-2" id="status-selector" disabled>
                                                <option value="2" selected>Reject Payment</option>
                                            </select>
                                            <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                        </div>

                                        <div class="col-12 d-flex flex-column">
                                            <label class="text-14 main-text" for="">Note</label>
                                            <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                            <span id="note-feedback" class="text-dark-red text-12"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="col-12 col-md-4 custom-btn" onclick="updateLeadStatus(this,<?= $leadId ?>);">Update</button>
                            </div>
                        </div>
                    </div>
                </div>



                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>
            </body>

            </html>
<?php
        }
    }
}
?>