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

if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 1))) {
    header("Location: ./registrations");
    exit;
}

if (!isset($_GET["registration-id"]) || !is_numeric($_GET["registration-id"])) {
    header("Location: ./registrations");
    exit;
}

$leadId = (int) $_GET["registration-id"];

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
    WHERE shi.students_has_intakes_id = '" . $leadId . "'
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
    WHERE `students_has_intakes_students_has_intakes_id` = '" . (int)$leadId . "'
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

    $commonDocuments = [5, 10];
    $haveAccessEdt = $lead['enrolled_status'] == 'PENDING' ? true : false;

    $existingDocuments[$docKey][] = [
        "id" => $doc["students_identity_document_id"],
        "type_id" => $typeId,
        "label" => $label,
        "name" => $doc["document"],
        "url" => "assets/uploads/student-identity-documents/" . $folder . "/" . $doc["document"],
        "date_added" => $doc["date_added"],
        "access_remove" => (in_array($typeId, $commonDocuments) && $haveAccessEdt) || $typeId == 8 ? false : true,
    ];
}

$existingDocumentsJson = json_encode(
    $existingDocuments,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title>Inquiry View | <?= Project::getBaseName(); ?></title>

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
                                            <span class="main-topic lh-lg" data-i18n="">Upload Other Documents</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Inquiries</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Upload Other Documents</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-12 col-xxl-4 mt-3 mt-xxl-0">
                                        <div class="row gx-3 justify-content-end">
                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">

                                                <button class="custom-btn w-100" onclick="uploadOtherDocuments(this,<?= $leadId ?>);">
                                                    Upload
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 p-0 g-0">
                                <div class="row" id="student-documents-wrapper">
                                    <!-- AIC Application -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="aic_application">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">AIC Application</span>
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
                                                    id="doc-aic-application"
                                                    data-doc-key="aic_application"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-aic-application-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- University Application -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="university_application">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">University Application</span>
                                                <!-- <span class="doc-required">Required</span> -->
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
                                                    id="doc-university-application"
                                                    data-doc-key="university_application"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-university-application-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Signed Agreement -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="signed_agreement">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Signed Agreement with Letters</span>
                                                <!-- <span class="doc-required">Required</span> -->
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
                                                    id="doc-signed-agreement"
                                                    data-doc-key="signed_agreement"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-signed-agreement-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Signed Program Brief -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="signed_program_brief">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Signed Program Brief</span>
                                                <!-- <span class="doc-required">Required</span> -->
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
                                                    id="doc-signed-program-brief"
                                                    data-doc-key="signed_program_brief"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-signed-program-brief-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Passport / NIC -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="passport_nic">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Passport / NIC</span>
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
                                                    id="doc-passport-nic"
                                                    data-doc-key="passport_nic"
                                                    accept=".pdf,.jpg,.jpeg,.png">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-passport-nic-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Academic Documents -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="academic_documents">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Academic Documents</span>
                                                <!-- <span class="doc-required">Required</span> -->
                                            </div>

                                            <div class="doc-drop-area">
                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                <span class="doc-drop-text">Drag & Drop files here</span>
                                                <span class="doc-or-text">OR</span>

                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                    Browse Files
                                                </button>

                                                <input type="file"
                                                    class="d-none student-doc-input"
                                                    id="doc-academic-documents"
                                                    data-doc-key="academic_documents"
                                                    multiple
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-academic-documents-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Other Entry Documents -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="other_entry_documents">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Other Entry Documents</span>
                                                <!-- <span class="doc-required">Required</span> -->
                                            </div>

                                            <div class="doc-drop-area">
                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                <span class="doc-drop-text">Drag & Drop files here</span>
                                                <span class="doc-or-text">OR</span>

                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                    Browse Files
                                                </button>

                                                <input type="file"
                                                    class="d-none student-doc-input"
                                                    id="doc-other-entry-documents"
                                                    data-doc-key="other_entry_documents"
                                                    multiple
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-other-entry-documents-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Payment Slip -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="payment_slip">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Payment Slip</span>
                                                <span class="doc-required">Required</span>
                                            </div>

                                            <div class="doc-drop-area d-none">
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
                                        </div>
                                    </div>

                                    <!-- Check List Signed by Manager -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="manager_checklist">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Check List Document Signed by Manager</span>
                                                <!-- <span class="doc-required">Required</span> -->
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
                                                    id="doc-manager-checklist"
                                                    data-doc-key="manager_checklist"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-manager-checklist-feedback"></span>
                                        </div>
                                    </div>
                                    <!-- Discount Approved Document -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="passport_size_photo">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Passport Size Photo</span>
                                                <!-- <span class="doc-required">Required</span> -->
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
                                                    id="passport_size_photo"
                                                    data-doc-key="passport_size_photo"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="passport_size_photo-feedback"></span>
                                        </div>
                                    </div>

                                    <!--Signed fee structure  -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="signed_fee_structure">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Signed fee structure</span>
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
                                                    id="doc-signed-fee-structure"
                                                    data-doc-key="discount_approved_document"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-signed-fee-structure-feedback"></span>
                                        </div>
                                    </div>

                                    <!-- Discount Approved Document -->
                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                        <div class="student-doc-upload-box" data-doc-key="discount_approved_document">
                                            <div class="doc-upload-header">
                                                <span class="doc-title">Discount Approved Document</span>
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
                                                    id="doc-discount-approved-document"
                                                    data-doc-key="discount_approved_document"
                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>

                                            <div class="doc-preview mt-2"></div>
                                            <span class="text-dark-red text-12 doc-feedback" id="doc-discount-approved-document-feedback"></span>
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