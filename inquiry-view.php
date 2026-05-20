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

if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('students', 1))) {
    header("Location: ./inquiries");
    exit;
}

if (!isset($_GET["inquiry-id"]) || !is_numeric($_GET["inquiry-id"])) {
    header("Location: ./inquiries");
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
    // header("Location: ./inquiries");
    // exit;
}

$lead = $leadRs->fetch_assoc();

$address = null;

$addressRs = Database::search("
    SELECT 
        sha.*,
        c.city,
        d.district,
        pr.province
    FROM student_has_address sha
    LEFT JOIN city c 
        ON sha.city_city_id = c.city_id
    LEFT JOIN district d 
        ON c.district_district_id = d.district_id
    LEFT JOIN province pr 
        ON d.province_province_id = pr.province_id
    WHERE sha.students_student_id = '" . $leadId . "'
    LIMIT 1
");

if ($addressRs->num_rows > 0) {
    $address = $addressRs->fetch_assoc();
}

$guardian = null;

$guardianRs = Database::search("
    SELECT 
        gd.*,
        gr.guardian_relation,
        t.title
    FROM guardian_details gd
    LEFT JOIN guardian_relation gr 
        ON gd.guardian_relation_guardian_relation_id = gr.guardian_relation_id
    LEFT JOIN title t 
        ON gd.title_title_id = t.title_id
    WHERE gd.students_student_id = '" . $leadId . "'
    LIMIT 1
");

if ($guardianRs->num_rows > 0) {
    $guardian = $guardianRs->fetch_assoc();
}

$documentsRs = Database::search("
    SELECT *
    FROM students_identity_documents
    WHERE students_student_id = '" . $leadId . "'
    ORDER BY students_identity_documents_type_id ASC, date_added DESC
");

$profileImage = "./assets/imgs/common/empty-img.png";

if (!empty($lead["profile_img"])) {
    $profileImage = "./assets/imgs/students/" . $lead["profile_img"];
}
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

    <style>
        .lead-layout-card {
            border: 1px solid var(--line-color);
            background: var(--custom-white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .lead-profile-sidebar {
            position: sticky;
            top: 20px;
            overflow: hidden;
        }

        .lead-cover {
            height: 110px;
            background: linear-gradient(135deg, var(--dark-theme), #2e86de);
            position: relative;
        }

        .lead-cover::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            background: rgba(255, 255, 255, 0.08);
            right: -90px;
            top: -90px;
            border-radius: 50%;
        }

        .lead-profile-area {
            margin-top: -65px;
            position: relative;
            z-index: 2;
        }

        .lead-profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 4px solid #fff;
            background: #fff;
        }

        .lead-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--light-theme);
            color: var(--dark-theme);
            font-size: 12px;
            font-weight: 600;
            border-left: 3px solid var(--dark-theme);
        }

        .lead-contact-item {
            padding: 16px 0;
            border-bottom: 1px dashed var(--line-color);
        }

        .lead-contact-item:last-child {
            border-bottom: 0;
        }

        .lead-contact-icon {
            width: 42px;
            height: 42px;
            background: var(--light-theme);
            color: var(--dark-theme);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lead-main-card {
            border: 1px solid var(--line-color);
            background: var(--custom-white);
        }

        .lead-main-header {
            padding: 24px;
            border-bottom: 1px solid var(--line-color);
            background: var(--custom-white);
        }

        .lead-main-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--dark-theme);
        }

        .lead-main-sub {
            color: var(--sub-text);
            font-size: 13px;
        }

        .lead-accordion .accordion-item {
            border: 0;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .lead-accordion .accordion-button {
            box-shadow: none !important;
            border: 1px solid var(--line-color);
            padding: 18px 22px;
            font-size: 15px;
            font-weight: 600;
            border-left: 4px solid var(--dark-theme);
            background: var(--custom-white);
        }

        .lead-accordion .accordion-button:not(.collapsed) {
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .lead-accordion .accordion-body {
            border: 1px solid var(--line-color);
            border-top: 0;
            padding: 0;
            background: var(--custom-white);
        }

        .lead-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .lead-detail-item {
            padding: 20px;
            border-right: 1px solid var(--line-color);
            border-bottom: 1px solid var(--line-color);
            min-height: 100px;
        }

        .lead-detail-label {
            font-size: 11px;
            color: var(--sub-text);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .lead-detail-value {
            font-size: 14px;
            color: var(--main-text-color);
            font-weight: 600;
            word-break: break-word;
            line-height: 1.6;
        }

        .lead-doc-card {
            border: 1px solid var(--line-color);
            transition: .2s;
            background: #fff;
        }

        .lead-doc-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, .06);
        }

        .lead-doc-icon {
            width: 55px;
            height: 55px;
            background: var(--light-theme);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-theme);
        }

        .lead-view-btn {
            height: 38px;
            padding: 0 18px;
            font-size: 13px;
            font-weight: 600;
        }

        @media(max-width:991px) {
            .lead-profile-sidebar {
                position: relative;
                top: unset;
            }
        }
    </style>

</head>

<body class="overflow-hidden">

    <div class="notifications"></div>

    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">

            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("Inquiries");
                ?>
            </div>

            <div id="app-wrapper" class="app-wrapper p-0 m-0">

                <div class="row p-0 m-0 g-0">

                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "./includes/header.php";
                        selectItem::setSelected("Inquiries");
                        ?>
                    </div>

                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column">
                        <div class="row p-4 m-0 g-4">
                            <div class="col-12 px-4 py-3 component-theme mb-4 mt-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 col-md-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg" data-i18n="">Inquiry View</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payments">Inquiries</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Inquiry View</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- LEFT -->
                            <div class="col-12 col-xl-3 m-0 px-0">

                                <div class="lead-layout-card lead-profile-sidebar">

                                    <div class="lead-cover"></div>

                                    <div class="p-4 text-center lead-profile-area">

                                        <img src="<?= h($profileImage) ?>"
                                            class="lead-profile-image mb-3"
                                            alt="profile">

                                        <h4 class="main-text fw-bold mb-1">
                                            <?= showValue($lead["full_name"]) ?>
                                        </h4>

                                        <div class="sub-text text-13 mb-3">
                                            <?= showValue($lead["programme"]) ?>
                                        </div>

                                        <div class="lead-status-badge">
                                            <iconify-icon icon="solar:user-id-bold" width="16"></iconify-icon>
                                            Lead ID #<?= h($lead["student_id"]) ?>
                                        </div>

                                        <div class="mt-4 text-start">

                                            <div class="lead-contact-item d-flex align-items-start gap-3">
                                                <div class="lead-contact-icon">
                                                    <iconify-icon icon="solar:phone-outline" width="18"></iconify-icon>
                                                </div>

                                                <div>
                                                    <div class="sub-text text-12">Mobile</div>
                                                    <div class="main-text text-14 fw-semibold">
                                                        <?= showValue($lead["mobile"]) ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="lead-contact-item d-flex align-items-start gap-3">
                                                <div class="lead-contact-icon">
                                                    <iconify-icon icon="ic:baseline-whatsapp" width="18"></iconify-icon>
                                                </div>

                                                <div>
                                                    <div class="sub-text text-12">WhatsApp</div>
                                                    <div class="main-text text-14 fw-semibold">
                                                        <?= showValue($lead["whatsapp"]) ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="lead-contact-item d-flex align-items-start gap-3">
                                                <div class="lead-contact-icon">
                                                    <iconify-icon icon="solar:letter-outline" width="18"></iconify-icon>
                                                </div>

                                                <div>
                                                    <div class="sub-text text-12">Email</div>
                                                    <div class="main-text text-14 fw-semibold">
                                                        <?= showValue($lead["email"]) ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="lead-contact-item d-flex align-items-start gap-3">
                                                <div class="lead-contact-icon">
                                                    <iconify-icon icon="solar:map-point-outline" width="18"></iconify-icon>
                                                </div>

                                                <div>
                                                    <div class="sub-text text-12">Address</div>
                                                    <div class="main-text text-14 fw-semibold">
                                                        <?= showValue($address["address_line_1"] ?? null) ?>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <!-- RIGHT -->
                            <div class="col-12 col-xl-9 m-0 pe-0">

                                <div class="lead-main-card">
                                    <div class="p-4">
                                        <div class="accordion lead-accordion" id="leadAccordion">
                                            <!-- PERSONAL -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#personalDetails">

                                                        <iconify-icon icon="solar:user-id-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Personal Details
                                                    </button>
                                                </h2>

                                                <div id="personalDetails"
                                                    class="accordion-collapse collapse show"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <?php
                                                            $personal = [
                                                                "Full Name" => $lead["full_name"],
                                                                "Name With Initials" => $lead["name_with_initials"],
                                                                "Gender" => $lead["gender"],
                                                                "Date of Birth" => $lead["date_of_birth"],
                                                                "NIC" => $lead["nic"],
                                                                "Passport" => $lead["passport"],
                                                                "Marital Status" => $lead["marital_status"],
                                                                "Nationality" => $lead["nationality_name"],
                                                                "Country of Birth" => $lead["birth_country_name"],
                                                                "Country of Residence" => $lead["residence_country_name"]
                                                            ];

                                                            foreach ($personal as $label => $value) {
                                                            ?>

                                                                <div class="lead-detail-item">
                                                                    <div class="lead-detail-label"><?= h($label) ?></div>
                                                                    <div class="lead-detail-value"><?= showValue($value) ?></div>
                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- CONTACT -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#contactDetails">

                                                        <iconify-icon icon="solar:phone-calling-rounded-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Contact Details
                                                    </button>
                                                </h2>

                                                <div id="contactDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <?php
                                                            $contact = [
                                                                "Email" => $lead["email"],
                                                                "Mobile" => $lead["mobile"],
                                                                "WhatsApp" => $lead["whatsapp"]
                                                            ];

                                                            foreach ($contact as $label => $value) {
                                                            ?>

                                                                <div class="lead-detail-item">
                                                                    <div class="lead-detail-label"><?= h($label) ?></div>
                                                                    <div class="lead-detail-value"><?= showValue($value) ?></div>
                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- ADDRESS -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#addressDetails">

                                                        <iconify-icon icon="solar:map-point-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Address Details
                                                    </button>
                                                </h2>

                                                <div id="addressDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <?php
                                                            $addresses = [
                                                                "Address Line 1" => $address["address_line_1"] ?? null,
                                                                "Address Line 2" => $address["address_line_2"] ?? null,
                                                                "Province" => $address["province"] ?? null,
                                                                "District" => $address["district"] ?? null,
                                                                "City" => $address["city"] ?? null,
                                                                "Postal Code" => $address["postal_code"] ?? null
                                                            ];

                                                            foreach ($addresses as $label => $value) {
                                                            ?>

                                                                <div class="lead-detail-item">
                                                                    <div class="lead-detail-label"><?= h($label) ?></div>
                                                                    <div class="lead-detail-value"><?= showValue($value) ?></div>
                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- GUARDIAN -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#guardianDetails">

                                                        <iconify-icon icon="solar:users-group-rounded-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Guardian Details
                                                    </button>
                                                </h2>

                                                <div id="guardianDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <?php
                                                            $guardians = [
                                                                "Relationship" => $guardian["guardian_relation"] ?? null,
                                                                "Title" => $guardian["title"] ?? null,
                                                                "First Name" => $guardian["first_name"] ?? null,
                                                                "Last Name" => $guardian["last_name"] ?? null,
                                                                "Email" => $guardian["email"] ?? null,
                                                                "Mobile" => $guardian["mobile"] ?? null,
                                                                "WhatsApp" => $guardian["whatsapp"] ?? null
                                                            ];

                                                            foreach ($guardians as $label => $value) {
                                                            ?>

                                                                <div class="lead-detail-item">
                                                                    <div class="lead-detail-label"><?= h($label) ?></div>
                                                                    <div class="lead-detail-value"><?= showValue($value) ?></div>
                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- ACADEMIC -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#academicDetails">

                                                        <iconify-icon icon="solar:notebook-bookmark-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Academic Details
                                                    </button>
                                                </h2>

                                                <div id="academicDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <?php
                                                            $academic = [
                                                                "Programme" => $lead["programme"],
                                                                "Entry Type" => $lead["entry_type"],
                                                                "Payment Option" => $lead["payment_option"]
                                                            ];

                                                            foreach ($academic as $label => $value) {
                                                            ?>

                                                                <div class="lead-detail-item">
                                                                    <div class="lead-detail-label"><?= h($label) ?></div>
                                                                    <div class="lead-detail-value"><?= showValue($value) ?></div>
                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- DOCUMENTS -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#documentsDetails">

                                                        <iconify-icon icon="solar:folder-with-files-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        Uploaded Documents
                                                    </button>
                                                </h2>

                                                <div id="documentsDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body p-4">

                                                        <div class="row g-3">

                                                            <?php while ($doc = $documentsRs->fetch_assoc()) {

                                                                $folder = getDocFolderByType($doc["students_identity_documents_type_id"]);

                                                                $docPath = "./assets/uploads/student-identity-documents/" . $folder . "/" . $doc["document"];
                                                            ?>

                                                                <div class="col-12 col-md-6">

                                                                    <div class="lead-doc-card p-3">

                                                                        <div class="d-flex align-items-center gap-3">

                                                                            <div class="lead-doc-icon">
                                                                                <iconify-icon icon="solar:file-text-bold"
                                                                                    width="24"></iconify-icon>
                                                                            </div>

                                                                            <div class="flex-grow-1">

                                                                                <div class="main-text fw-semibold text-14">
                                                                                    <?= h(getDocTitleByType($doc["students_identity_documents_type_id"])) ?>
                                                                                </div>

                                                                                <div class="sub-text text-12">
                                                                                    <?= showValue($doc["date_added"]) ?>
                                                                                </div>

                                                                            </div>

                                                                            <a href="<?= h($docPath) ?>"
                                                                                target="_blank"
                                                                                class="custom-btn lead-view-btn text-decoration-none d-flex align-items-center">

                                                                                View

                                                                            </a>

                                                                        </div>

                                                                    </div>

                                                                </div>

                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                            <!-- SYSTEM -->
                                            <div class="accordion-item">

                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#systemDetails">

                                                        <iconify-icon icon="solar:settings-bold"
                                                            width="20"
                                                            class="me-2"></iconify-icon>

                                                        System Details
                                                    </button>
                                                </h2>

                                                <div id="systemDetails"
                                                    class="accordion-collapse collapse"
                                                    data-bs-parent="#leadAccordion">

                                                    <div class="accordion-body">

                                                        <div class="lead-detail-grid">

                                                            <div class="lead-detail-item">
                                                                <div class="lead-detail-label">Added By</div>
                                                                <div class="lead-detail-value">
                                                                    <?= showValue(trim(($lead["added_user_fname"] ?? "") . " " . ($lead["added_user_lname"] ?? ""))) ?>
                                                                </div>
                                                            </div>

                                                            <div class="lead-detail-item">
                                                                <div class="lead-detail-label">Date Added</div>
                                                                <div class="lead-detail-value">
                                                                    <?= showValue($lead["date_added"]) ?>
                                                                </div>
                                                            </div>

                                                            <div class="lead-detail-item">
                                                                <div class="lead-detail-label">Lead ID</div>
                                                                <div class="lead-detail-value">
                                                                    #<?= h($lead["student_id"]) ?>
                                                                </div>
                                                            </div>

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
                    <h5 class="modal-title" id="exampleModalLongTitle">Update Inquiry Status</h5>
                </div>
                <div class="modal-body">
                    <div class="col-12 p-0 g-0">
                        <div class="row gx-4 gy-3">
                            <div class="col-12 d-flex flex-column">
                                <label class="text-14 main-text required" for="">Inquiry Status</label>
                                <select class="form-select custom-select mt-2" id="status-selector">
                                    <?php
                                    $query = "SELECT * FROM `lead_student_status` WHERE `lead_student_status_id` != '1'";
                                    if ($lead['lead_student_status_lead_student_status_id'] == 2) {
                                        $query = "SELECT * FROM `lead_student_status` WHERE `lead_student_status_id` = '4'";
                                    }
                                    $status = Database::search($query);
                                    while ($data = $status->fetch_assoc()) {
                                    ?>
                                        <option value="<?= $data["lead_student_status_id"]; ?>"><?= $data["lead_student_status"]; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                                <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                            </div>
                            <div class="col-12 d-flex flex-column <?= $lead['lead_student_status_lead_student_status_id'] == 2 ? '' : 'd-none' ?>">
                                <label class="text-14 main-text required" for="">Intakes</label>
                                <select id="intake-selector" class="form-select custom-select mt-2">
                                    <option value="0">- Select -</option>
                                    <?php
                                    $intakesResult = Database::search("SELECT * FROM `intakes` WHERE `programmes_programme_id` = '" . $lead['programmes_programme_id'] . "' ORDER BY `intake_id` ASC");
                                    while ($data = $intakesResult->fetch_assoc()) {
                                    ?>
                                        <option value="<?= $data["intake_id"]; ?>"><?= $data["intake"]; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                                <span class="text-dark-red text-12" id="intake-feedback"></span>
                            </div>
                            <div class="col-12 d-flex flex-column <?= $lead['lead_student_status_lead_student_status_id'] == 2 ? '' : 'd-none' ?>">
                                <label class="text-14 main-text required" for="">Student Intake Id</label>
                                <input type="text" class="form-control custom-input mt-2" id="student-intake-id">
                                <span id="student-intake-id-feedback" class="text-dark-red text-12"></span>
                            </div>

                            <div class="col-12 d-flex flex-column">
                                <label class="text-14 main-text required" for="">Note</label>
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

</body>

</html>