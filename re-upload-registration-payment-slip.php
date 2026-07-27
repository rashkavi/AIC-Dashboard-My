<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();

if ($sessionUserRole == null) {
    header("Location: ./index");
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 1))) {
    header("Location: ./registrations");
    exit;
}

if (!isset($_GET["inquiry-id"]) || !is_numeric($_GET["inquiry-id"])) {
    header("Location: ./registrations");
    exit;
}

$leadId = (int) $_GET["inquiry-id"];

function h($value)
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

function showValue($value)
{
    return !empty($value) ? h($value) : "-";
}

function getDocFolderByType($typeId)
{
    $map = [
        1 => "aic-applications",
        2 => "university-applications",
        3 => "signed-agreements",
        4 => "signed-program-briefs",
        5 => "nic-documents",
        6 => "academic-documents",
        7 => "other-entry-documents",
        8 => "payment-slips",
        9 => "manager-checklists",
        10 => "passport-size-photo",
        11 => "discount-approved-documents"
    ];

    return $map[(int)$typeId] ?? "";
}

function getDocTitleByType($typeId)
{
    $map = [
        1 => "AIC Application",
        2 => "University Application",
        3 => "Signed Agreement",
        4 => "Signed Program Brief",
        5 => "Passport / NIC",
        6 => "Academic Documents",
        7 => "Other Entry Documents",
        8 => "Payment Slip",
        9 => "Manager Checklist",
        10 => "Passport Size Photo",
        11 => "Discount Approved Document"
    ];

    return $map[(int)$typeId] ?? "Document";
}

$leadRs = Database::search("
    SELECT 
        s.*,
        ld.first_name AS login_first_name,
        ld.last_name AS login_last_name,
        ld.email,
        ld.whatsapp,
        ld.first_name,
        ld.last_name,
        t.title,
        g.gender,
        ms.marital_status,
        nc.country_name AS nationality_name,
        cob.country_name AS birth_country_name,
        cor.country_name AS residence_country_name,
        i.intake,
        p.programme,
        u.user_fname AS added_user_fname,
        u.user_lname AS added_user_lname
    FROM students s
    INNER JOIN login_details ld 
        ON s.login_details_login_details_id = ld.login_details_id
    LEFT JOIN title t 
        ON s.title_title_id = t.title_id
    LEFT JOIN gender g 
        ON s.gender_gender_id = g.gender_id
    LEFT JOIN marital_status ms 
        ON s.marital_status_marital_status_id = ms.marital_status_id
    LEFT JOIN countries nc 
        ON s.nationality = nc.country_id
    LEFT JOIN countries cob 
        ON s.country_of_birth = cob.country_id
    LEFT JOIN countries cor 
        ON s.country_of_residence = cor.country_id
    INNER JOIN students_has_intakes shi 
        ON s.student_id = shi.students_student_id
    INNER JOIN intakes i 
        ON shi.intakes_intake_id = i.intake_id
    INNER JOIN programmes p
        ON i.programmes_programme_id = p.programme_id
    LEFT JOIN users u 
        ON s.users_user_id = u.user_id
    WHERE s.student_id = '" . $leadId . "'
    LIMIT 1
");

if ($leadRs->num_rows == 0) {
    header("Location: ./registrations");
    exit;
}

$lead = $leadRs->fetch_assoc();

$existingDocuments = [
    "aic_application" => [],
    "university_application" => [],
    "signed_agreement" => [],
    "signed_program_brief" => [],
    "passport_nic" => [],
    "academic_documents" => [],
    "other_entry_documents" => [],
    "payment_slip" => [],
    "manager_checklist" => [],
    "passport_size_photo" => [],
    "discount_approved_document" => [],
];

$documentTypeMap = [
    1 => [
        "key" => "aic_application",
        "label" => "AIC Application",
        "folder" => "aic-applications",
    ],
    2 => [
        "key" => "university_application",
        "label" => "University Application",
        "folder" => "university-applications",
    ],
    3 => [
        "key" => "signed_agreement",
        "label" => "Signed Agreement",
        "folder" => "signed-agreements",
    ],
    4 => [
        "key" => "signed_program_brief",
        "label" => "Signed Program Brief",
        "folder" => "signed-program-briefs",
    ],
    5 => [
        "key" => "passport_nic",
        "label" => "Passport / NIC",
        "folder" => "passport-nic",
    ],
    6 => [
        "key" => "academic_documents",
        "label" => "Academic Documents",
        "folder" => "academic-documents",
    ],
    7 => [
        "key" => "other_entry_documents",
        "label" => "Other Entry Documents",
        "folder" => "other-entry-documents",
    ],
    8 => [
        "key" => "payment_slip",
        "label" => "Payment Slip",
        "folder" => "payment-slips",
    ],
    9 => [
        "key" => "manager_checklist",
        "label" => "Manager Checklist",
        "folder" => "manager-checklists",
    ],
    10 => [
        "key" => "passport_size_photo",
        "label" => "Passport Size Photo",
        "folder" => "passport-size-photos",
    ],
    11 => [
        "key" => "discount_approved_document",
        "label" => "Discount Approved Document",
        "folder" => "discount-approved-documents",
    ],
];

$existingDocRs = Database::search("
    SELECT *
    FROM `students_identity_documents`
    WHERE `students_student_id` = '" . (int)$leadId . "'
    ORDER BY `students_identity_documents_type_id` ASC, `date_added` DESC
");

while ($doc = $existingDocRs->fetch_assoc()) {
    $typeId = (int)$doc["students_identity_documents_type_id"];

    if (!isset($documentTypeMap[$typeId])) {
        continue;
    }

    $docKey = $documentTypeMap[$typeId]["key"];
    $folder = $documentTypeMap[$typeId]["folder"];
    $label = $documentTypeMap[$typeId]["label"];

    $existingDocuments[$docKey][] = [
        "id" => $doc["students_identity_document_id"],
        "type_id" => $typeId,
        "label" => $label,
        "name" => $doc["document"],
        "url" => "assets/uploads/student-identity-documents/" . $folder . "/" . $doc["document"],
        "date_added" => $doc["date_added"],
        "access_remove" => $typeId == 8 ? false : true
    ];
}

$existingDocumentsJson = json_encode(
    $existingDocuments,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
);

$log = Database::search("SELECT * FROM `students_logs` WHERE `students_student_id` = '" . (int)$leadId . "' 
AND `lead_student_status_lead_student_status_id`='2' AND `note` IS NOT NULL ORDER BY `date_updated` DESC LIMIT 1");
if ($log->num_rows > 0) {
    $log = $log->fetch_assoc();
    $paymentRejectReason = $log['note'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title>Re Upload Registration Payment Slip | <?= Project::getBaseName(); ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
</head>

<body class="overflow-hidden" onload="initializeUpdateStudentDocumentUploads();">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">

            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("Registrations");
                ?>
            </div>

            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "./includes/header.php";
                         selectItem::setSelected("Registrations");
                        ?>
                    </div>

                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column">
                        <div class="row p-4 m-0 g-4">
                            <div class="col-12 px-4 py-3 component-theme mt-0 mb-4">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 col-md-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg" data-i18n="">Re-Upload Registration Payment Slip</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Inquiries</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Re-Upload Registration Payment Slip</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="payment-card-container mb-3 mt-0 p-0">
                                <div class="payment-card">
                                    <!-- Background elements -->
                                    <div class="card-background-cancelled"></div>

                                    <!-- Card content -->
                                    <div class="card-content">
                                        <div class="payment-header reference-section">
                                            <!-- Reference on left -->
                                            <div class="reference-section">
                                                <span class="reference-label">Student Name</span>
                                                <h2 class="reference-number"><?php echo $lead['title'] . "." . $lead['first_name'] . " " . $lead['last_name']; ?> ( <?= $lead['student_id'] ?> )</h2>
                                                <hr>
                                            </div>

                                        </div>

                                        <div class="details-row d-flex flex-column flex-lg-row">
                                            <div class="detail-item">
                                                <div class="detail-icon">
                                                    <iconify-icon icon="ic:outline-email" width="20" height="20" class="text-white"></iconify-icon>

                                                </div>
                                                <div class="detail-content">
                                                    <span class="detail-label">Email</span>
                                                    <span class="detail-value"><?= $lead['email']; ?></span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-icon">
                                                    <iconify-icon icon="material-symbols:call-outline" width="20" height="20" class="text-white"></iconify-icon>

                                                </div>
                                                <div class="detail-content">
                                                    <span class="detail-label">Mobile</span>
                                                    <span class="detail-value"><?= $lead['mobile'] == null ? 'N/A' : $lead['mobile']; ?></span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-icon">
                                                    <iconify-icon icon="ic:baseline-whatsapp" width="20" height="20" class="text-white"></iconify-icon>
                                                </div>
                                                <div class="detail-content">
                                                    <span class="detail-label">WhatsApp</span>
                                                    <span class="detail-value"><?= $lead['whatsapp'] == null ? 'N/A' : $lead['whatsapp']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>



                            <div class="col-12 mb-3 p-0 br-0">
                                <div class="bl-solid p-3 bg-light-red2 br-0">

                                    <div class="d-flex align-items-start br-0">
                                        <div class="hw-40 bg-light-red br-8 me-3 flex-shrink-0">
                                            <iconify-icon icon="solar:danger-triangle-bold"
                                                width="22"
                                                height="22"
                                                class="text-dark-red"></iconify-icon>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                <span class="text-16 fw-semibold main-text">
                                                    Payment Slip Re-upload Required
                                                </span>

                                                <span class="table-btn table-btn-danger text-12 px-3 py-1">
                                                    Rejected
                                                </span>
                                            </div>

                                            <p class="text-14 sub-text mb-3">
                                                Your submitted payment slip could not be verified. Please review the rejection note
                                                and upload a clear, valid payment slip again.
                                            </p>

                                            <div class="component-theme bl-solid br-8 p-3 mb-3">
                                                <span class="text-12 fw-semibold text-dark-red d-block mb-1">
                                                    Rejection Note
                                                </span>

                                                <p class="text-14 main-text mb-0">
                                                    <?= !empty($paymentRejectReason)
                                                        ? htmlspecialchars($paymentRejectReason)
                                                        : 'Payment slip was rejected. Please upload a valid slip with clear transaction details.' ?>
                                                </p>
                                            </div>

                                            <div class="row gy-2">
                                                <div class="col-12 col-md-6">
                                                    <div class="d-flex align-items-start">
                                                        <iconify-icon icon="solar:check-circle-bold"
                                                            width="18"
                                                            height="18"
                                                            class="text-dark-green me-2 mt-1"></iconify-icon>

                                                        <span class="text-13 sub-text">
                                                            Transaction reference number must be visible.
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="d-flex align-items-start">
                                                        <iconify-icon icon="solar:check-circle-bold"
                                                            width="18"
                                                            height="18"
                                                            class="text-dark-green me-2 mt-1"></iconify-icon>

                                                        <span class="text-13 sub-text">
                                                            Paid amount and payment date must be readable.
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="d-flex align-items-start">
                                                        <iconify-icon icon="solar:check-circle-bold"
                                                            width="18"
                                                            height="18"
                                                            class="text-dark-green me-2 mt-1"></iconify-icon>

                                                        <span class="text-13 sub-text">
                                                            Upload only PDF, JPG, JPEG, or PNG files.
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="d-flex align-items-start">
                                                        <iconify-icon icon="solar:check-circle-bold"
                                                            width="18"
                                                            height="18"
                                                            class="text-dark-green me-2 mt-1"></iconify-icon>

                                                        <span class="text-13 sub-text">
                                                            Avoid blurred, cropped, or edited screenshots.
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="col-12 p-0 g-0">
                                <div class="row" id="student-documents-wrapper">

                                    <!-- Payment Slip -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="payment_slip">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Payment Slip</span>
                                                <span class="doc-required">Required</span>
                                            </div>

                                            <div class="doc-drop-area">
                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                <span class="doc-or-text">OR</span>

                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                    Browse File
                                                </button>

                                                <input type="file"
                                                    class="d-none student-doc-input"
                                                    id="doc-payment-slip"
                                                    data-doc-key="payment_slip"
                                                    accept=".pdf,.jpg,.jpeg,.png">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-payment-slip-feedback"></span>

                                            <button class="custom-btn w-100" onclick="reuploadRegistrationPaymentSlip(this,<?= $leadId ?>);">
                                                Upload
                                            </button>
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

    <script>
        window.EXISTING_STUDENT_DOCUMENTS = <?= $existingDocumentsJson ?>;
    </script>
    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

</body>

</html>