<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/Limitation.php";

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

// same permission rule as add-registration / api/addRegistration.php
if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCounsiler($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
    header("Location: ./students");
    exit;
}

$counselorLocked = SessionUser::ifUserIsCounsiler($sessionUserRole) && !SessionUser::ifUserIsBranchManager($sessionUserRole);

$branchesResult = Database::search("SELECT * FROM `branches` ORDER BY `branch_id` ASC");
$branches = [];
while ($data = $branchesResult->fetch_assoc()) {
    $branches[] = $data;
}

$facultiesResult = Database::search("SELECT * FROM `faculties` ORDER BY `faculty_id` ASC");
$faculties = [];
while ($data = $facultiesResult->fetch_assoc()) {
    $faculties[] = $data;
}

// Document boxes - same order / titles / accepted types as add-registration.php
$docAccept = ".pdf,.jpg,.jpeg,.png,.doc,.docx";
$docAcceptImg = ".pdf,.jpg,.jpeg,.png";
$docUi = [
    "aic_application"            => ["title" => "AIC Application",                         "accept" => $docAccept],
    "university_application"     => ["title" => "University Application",                  "accept" => $docAccept],
    "signed_agreement"           => ["title" => "Signed Agreement with Letters",           "accept" => $docAccept],
    "signed_program_brief"       => ["title" => "Signed Program Brief",                    "accept" => $docAccept],
    "passport_nic"               => ["title" => "Passport / NIC",                          "accept" => $docAcceptImg],
    "academic_documents"         => ["title" => "Academic Documents",                      "accept" => $docAccept, "multiple" => true],
    "other_entry_documents"      => ["title" => "Other Entry Documents",                   "accept" => $docAccept, "multiple" => true],
    "payment_slip"               => ["title" => "Payment Slip",                            "accept" => $docAcceptImg],
    "manager_checklist"          => ["title" => "Check List Document Signed by Manager",   "accept" => $docAccept],
    "passport_size_photo"        => ["title" => "Passport Size Photo",                     "accept" => $docAccept],
    "signed_fee_structure"       => ["title" => "Signed fee structure",                    "accept" => $docAccept],
    "discount_approved_document" => ["title" => "Discount Approved Document",              "accept" => $docAccept],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Existing Student Registration - Admin Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

</head>

<body class="overflow-hidden" onload="initializeStudentDocumentUploads();">
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

                    <div class="container p-4 m-0" id="skeleton-list">
                        <?php
                        include "./includes/components/skeleton-header.php";
                        include "./includes/components/skeleton-add-edit.php"; ?>
                    </div>

                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                        <div class="row p-4 m-0 g-0">

                            <!-- Page header -->
                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 col-md-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg">Existing Student Registration</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./registrations">Registrations</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">Existing Student Registration</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                        <div class="row h-100 justify-content-end align-items-center gx-3">
                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                <button class="custom-btn w-100" id="submitRegistrationBtnTop" onclick="submitExistingStudentRegistration(this)">Save Registration</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="formAlert" class="col-12"></div>

                            <!-- Search -->
                            <div class="col-12 p-4 component-theme mb-4">
                                <label class="text-14 main-text fw-semibold mb-2">Search Student</label>
                                <div class="position-relative" id="studentSearchWrapper">
                                    <div class="d-flex align-items-center p-1 bl-solid">
                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                        <input type="text" id="studentSearchInput" autocomplete="off"
                                            class="h-100 sub-text form-control border-0 shadow-none"
                                            placeholder="Search by name, NIC, passport, email or phone...">
                                        <div class="spinner-border spinner-border-sm text-secondary me-3 d-none" id="studentSearchSpinner" role="status"></div>
                                    </div>
                                    <div class="custom-options" id="studentSuggestions"></div>
                                </div>
                                <span class="text-12 sub-text mt-2 d-block">Type at least 2 characters.</span>
                            </div>

                            <!-- Everything below appears after a student is selected -->
                            <div class="col-12 d-none" id="studentSections">

                                <div class="p-4 component-theme mb-4">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <div class="d-flex align-items-center bg-light-theme px-3 py-2" id="selectedStudentChip"></div>
                                        <button type="button" class="btn btn-light br-0" onclick="clearSelectedStudent()">Change Student</button>
                                    </div>
                                    <span class="sub-topic">Student Details</span>
                                    <span class="text-12 sub-text d-block mb-2">View only</span>
                                    <div class="lead-layout-card mt-3">
                                        <div class="lead-detail-grid" id="basicDetails"></div>
                                    </div>
                                </div>

                                <div class="p-4 component-theme mb-4 d-none" id="advancedSection">
                                    <span class="sub-topic">Advanced Details</span>
                                    <span class="text-12 sub-text d-block mb-2">Only the details that have been filled in</span>
                                    <div class="lead-layout-card mt-3">
                                        <div class="lead-detail-grid" id="advancedDetails"></div>
                                    </div>
                                </div>

                                <div class="p-4 component-theme mb-4">
                                    <span class="sub-topic">Engaged Intakes</span>
                                    <div class="table-responsive mt-3">
                                        <table class="table bl-solid m-0">
                                            <thead>
                                                <tr>
                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Faculty</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Intake</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Branch</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Admission Date</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Entry Type</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="engagedIntakes"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="p-4 component-theme mb-4 d-none" id="documentsSection">
                                    <span class="sub-topic">Identity Documents on File</span>
                                    <span class="text-12 sub-text d-block">Student-level documents only (shared across intakes). Documents of other intakes are not shown.</span>
                                    <div class="row mt-2" id="existingDocuments"></div>
                                </div>

                                <form id="registrationForm" onsubmit="return false">

                                    <!-- Same markup / ids as the Advanced Profile Details block in add-registration.php -->
                                    <div class="p-4 component-theme">
                                        <div class="row gx-4 gy-3">
                                            <span class="sub-topic">New Intake</span>

                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Branches</label>
                                                <select id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties();">
                                                    <option value="0">- Select -</option>
                                                    <?php foreach ($branches as $data) { ?>
                                                        <option value="<?php echo $data["branch_id"]; ?>"><?php echo $data["branch"]; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <span class="text-dark-red text-12" id="branch-feedback"></span>
                                            </div>
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Faculties</label>
                                                <select id="faculty-selector" disabled class="form-select custom-select mt-2" disabled onchange="loadProgrammes();">
                                                    <option value="0" selected>- Select -</option>

                                                </select>
                                                <span class="text-dark-red text-12" id="faculty-feedback"></span>
                                            </div>
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Programmes</label>
                                                <select id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(2);">
                                                    <option value="0">- Select -</option>
                                                </select>
                                                <span class="text-dark-red text-12" id="programme-feedback"></span>
                                            </div>
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Intakes</label>
                                                <select id="intake-selector" name="intake" disabled class="form-select custom-select mt-2">
                                                    <option value="0">- Select -</option>
                                                </select>
                                                <span class="text-dark-red text-12" id="intake-feedback"></span>
                                                <span class="text-dark-red text-12" id="err-intake"></span>
                                            </div>

                                            <div class="col-12 col-md-4 d-flex flex-column <?= $counselorLocked ? 'd-none' : '' ?>">
                                                <label class="text-14 main-text required">Assign Counselor</label>
                                                <select id="user" name="counselor" class="form-select custom-select mt-2">
                                                    <option value="0" disabled <?= $counselorLocked ? '' : 'selected' ?>>- Select -</option>
                                                    <?php
                                                    $query = "SELECT * FROM `users`
                                                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                        WHERE `user_roles_user_role_id` IN (3,9,10) AND `status_status_id`='1'";
                                                    if ($counselorLocked) {
                                                        $query .= " AND `user_id`='" . $sessionUserObject['user_id'] . "'";
                                                    }
                                                    $users = Database::search($query);
                                                    while ($user = $users->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $user["user_id"]; ?>" <?= $counselorLocked ? 'selected' : '' ?>><?php echo $user["title"] . '.' . $user["user_fname"] . ' ' . $user["user_lname"]; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <span id="err-counselor" class="text-dark-red text-12"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Document fields: same boxes + JS as add-registration.php -->
                                    <div class="p-4 component-theme">
                                        <span class="sub-topic">Student Required Documents</span>
                                        <span class="text-12 sub-text d-block">Passport / NIC and Passport Size Photo are hidden when the student already has them on file.</span>

                                        <div class="row mt-2" id="student-documents-wrapper">
                                            <?php
                                            foreach ($docUi as $field => $ui) {
                                                $multi = !empty($ui['multiple']);
                                                $slug  = str_replace('_', '-', $field);
                                            ?>
                                                <div class="col-12 col-md-6 col-lg-4 mb-3 doc-col" data-doc-key="<?= $field ?>">
                                                    <div class="student-doc-upload-box" data-doc-key="<?= $field ?>">
                                                        <div class="doc-upload-header">
                                                            <span class="doc-title"><?= htmlspecialchars($ui['title']) ?></span>
                                                            <span class="doc-required d-none">Required</span>
                                                        </div>

                                                        <div class="doc-drop-area">
                                                            <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                            <span class="doc-drop-text">Drag & Drop file<?= $multi ? 's' : '' ?> here</span>
                                                            <span class="doc-or-text">OR</span>

                                                            <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                Browse File<?= $multi ? 's' : '' ?>
                                                            </button>

                                                            <input type="file"
                                                                class="d-none student-doc-input"
                                                                id="doc-<?= $slug ?>"
                                                                data-doc-key="<?= $field ?>"
                                                                <?= $multi ? 'multiple' : '' ?>
                                                                accept="<?= $ui['accept'] ?>">
                                                        </div>

                                                        <div class="doc-preview mt-2"></div>
                                                        <span class="text-dark-red text-12 doc-feedback" id="doc-<?= $slug ?>-feedback"></span>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- Same "Other" block as add-registration.php -->
                                    <div class="p-4 component-theme">
                                        <div class="row gx-4 gy-3">
                                            <span class="sub-topic">Other</span>

                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                <label class="text-14 main-text mb-2 required">Entry Type</label>
                                                <div class="d-flex align-items-center gap-4 mt-2">
                                                    <div class="form-check custom-entry-check">
                                                        <input class="form-check-input" type="radio" name="entry_type" id="academic-entry" value="ACADEMIC" checked>
                                                        <label class="form-check-label text-14 main-text" for="academic-entry">Academic Entry</label>
                                                    </div>
                                                    <div class="form-check custom-entry-check">
                                                        <input class="form-check-input" type="radio" name="entry_type" id="other-entry" value="OTHER">
                                                        <label class="form-check-label text-14 main-text" for="other-entry">Other Entry</label>
                                                    </div>
                                                </div>
                                                <span id="err-entry_type" class="text-dark-red text-12"></span>
                                            </div>
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text">Payment Option</label>
                                                <textarea class="form-control custom-input mt-2" id="payment-option" name="payment_option" rows="5"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 component-theme mb-4">
                                        <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                <button type="button" class="custom-btn w-100" id="submitRegistrationBtn" onclick="submitExistingStudentRegistration(this)">Save Registration</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
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
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
    <script>
        /*
         * Existing student registration page.
         * Depends on the add-registration document upload script (already loaded via functions.js / script.js):
         *   studentDocumentFiles, initializeStudentDocumentUploads(), syncStudentDocumentInput(),
         *   renderStudentDocumentPreview(), appendStudentDocumentsToFormData()
         * and on loadFaculties() / loadProgrammes() / loadIntakes().
         */
        (function() {
            const API = {
                search: './api/searchExistingStudents.php',
                details: './api/getExistingStudentDetails.php',
                submit: './api/addExistingStudentRegistration.php'
            };

            let selectedStudentId = 0;
            let engagedIntakeIds = [];
            let requiredDocuments = [];
            let searchTimer = null;
            let searchSeq = 0;

            const $ = (id) => document.getElementById(id);

            function esc(v) {
                return String(v === null || v === undefined ? '' : v)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function avatar(img, initials) {
                if (img) {
                    return '<img src="' + esc(img) + '" class="hwr-40" alt="Student image">';
                }
                return '<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">' + esc(initials) + '</div>';
            }

            function clearFieldErrors() {
                document.querySelectorAll('[id^="err-"]').forEach((el) => (el.textContent = ''));
                document.querySelectorAll('.student-doc-upload-box .doc-feedback').forEach((el) => (el.innerHTML = ''));
            }

            /* ------------------------------ documents ------------------------------ */

            function getBox(docKey) {
                return document.querySelector('.student-doc-upload-box[data-doc-key="' + docKey + '"]');
            }

            function clearDocumentBox(docKey) {
                const box = getBox(docKey);
                if (!box) return;
                studentDocumentFiles[docKey] = [];
                syncStudentDocumentInput(box.querySelector('.student-doc-input'), []);
                renderStudentDocumentPreview(box);
            }

            function resetDocuments() {
                document.querySelectorAll('.student-doc-upload-box').forEach((box) => clearDocumentBox(box.dataset.docKey));
            }

            // hide boxes for common documents already on file, mark required ones
            function applyDocumentRules(data) {
                requiredDocuments = data.required_documents || [];
                document.querySelectorAll('.doc-col').forEach((col) => {
                    const key = col.dataset.docKey;
                    const onFile = data.common_on_file && data.common_on_file[key] === true;
                    col.classList.toggle('d-none', onFile);
                    if (onFile) clearDocumentBox(key);
                    const badge = col.querySelector('.doc-required');
                    if (badge) badge.classList.toggle('d-none', onFile || requiredDocuments.indexOf(key) === -1);
                });
            }

            function validateDocuments() {
                let ok = true;
                requiredDocuments.forEach((key) => {
                    const box = getBox(key);
                    if (!box) return;
                    const col = box.closest('.doc-col');
                    if (col && col.classList.contains('d-none')) return;
                    if (!studentDocumentFiles[key] || studentDocumentFiles[key].length === 0) {
                        box.querySelector('.doc-feedback').innerHTML = 'This document is required';
                        ok = false;
                    }
                });
                return ok;
            }

            /* ------------------------------ search ------------------------------ */

            function hideSuggestions() {
                $('studentSuggestions').classList.remove('show');
            }

            function renderSuggestions(results) {
                const box = $('studentSuggestions');
                if (!results.length) {
                    box.innerHTML = '<div class="p-3 text-14 sub-text">No students found</div>';
                    box.classList.add('show');
                    return;
                }
                box.innerHTML = '<ul>' + results.map((r) => {
                    const meta = [r.id_number, r.email, r.mobile].filter(Boolean).map(esc).join(' &bull; ');
                    return '<li class="d-flex align-items-center" data-id="' + r.id + '">' +
                        avatar(r.image, r.initials) +
                        '<div class="ms-3 flex-grow-1">' +
                        '<div class="text-14 main-text fw-medium">' + esc(r.name) + '</div>' +
                        '<div class="text-12 sub-text">' + meta + '</div>' +
                        '</div>' +
                        '<span class="text-12 sub-text">' + r.intake_count + ' intake' + (r.intake_count === 1 ? '' : 's') + '</span>' +
                        '</li>';
                }).join('') + '</ul>';
                box.classList.add('show');
            }

            async function runSearch(q) {
                const seq = ++searchSeq;
                $('studentSearchSpinner').classList.remove('d-none');
                try {
                    const res = await fetch(API.search + '?q=' + encodeURIComponent(q), {
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (seq !== searchSeq) return;
                    if (data.error) {
                        showToast('error', esc(data.error));
                        return;
                    }
                    renderSuggestions(data.results || []);
                } catch (e) {
                    if (seq === searchSeq) showToast('error', 'Unable to search students right now.');
                } finally {
                    if (seq === searchSeq) $('studentSearchSpinner').classList.add('d-none');
                }
            }

            /* ------------------------------ details ------------------------------ */

            function renderKeyValues(containerId, items) {
                $(containerId).innerHTML = items.map((i) =>
                    '<div class="lead-detail-item">' +
                    '<div class="lead-detail-label">' + esc(i.label) + '</div>' +
                    '<div class="lead-detail-value">' + esc(i.value) + '</div>' +
                    '</div>'
                ).join('');
            }

            function renderIntakes(intakes) {
                if (!intakes.length) {
                    $('engagedIntakes').innerHTML = '<tr><td colspan="7" class="text-center sub-text text-14 py-4">This student is not engaged in any intake yet</td></tr>';
                    return;
                }
                $('engagedIntakes').innerHTML = intakes.map((i) =>
                    '<tr>' +
                    '<td class="px-4 py-2 align-middle text-14 main-text">' + esc(i.programme) + '</td>' +
                    '<td class="py-2 align-middle text-14 main-text">' + esc(i.faculty) + '</td>' +
                    '<td class="py-2 align-middle text-14 main-text">' + esc(i.intake) + '</td>' +
                    '<td class="py-2 align-middle text-14 main-text">' + esc(i.branch) + '</td>' +
                    '<td class="py-2 align-middle text-14 main-text">' + esc(i.admission_date) + '</td>' +
                    '<td class="py-2 align-middle text-14 main-text">' + esc(i.entry_type) + '</td>' +
                    '<td class="py-2 align-middle"><button type="button" class="table-btn label-student-status-' + i.status_id + '">' + esc(i.status) + '</button></td>' +
                    '</tr>'
                ).join('');
            }

            function renderExistingDocuments(docs) {
                if (!docs.length) {
                    $('documentsSection').classList.add('d-none');
                    return;
                }
                $('existingDocuments').innerHTML = docs.map((d) =>
                    '<div class="col-12 col-md-6 col-lg-4 mb-3"><div class="lead-doc-card p-3 d-flex align-items-center">' +
                    '<div class="lead-doc-icon"><iconify-icon icon="solar:document-broken" width="28" height="28"></iconify-icon></div>' +
                    '<div class="ms-3 flex-grow-1"><div class="text-14 fw-semibold" style="color:#1E2A4A">' + esc(d.type_name) + '</div>' +
                    '<div class="text-12 sub-text">' + esc(d.date) + '</div></div>' +
                    (d.url ? '<a href="' + esc(d.url) + '" target="_blank" rel="noopener" class="custom-btn lead-view-btn d-flex align-items-center text-decoration-none">View</a>' : '') +
                    '</div></div>'
                ).join('');
                $('documentsSection').classList.remove('d-none');
            }

            function resetRegistrationForm() {
                $('registrationForm').reset();
                // branch back to "- Select -" -> let the existing helper reset faculty / programme / intake
                try {
                    if (typeof loadFaculties === 'function') loadFaculties();
                } catch (e) {
                    /* ignore */
                }
                resetDocuments();
                clearFieldErrors();
            }

            async function loadStudent(id) {
                try {
                    const res = await fetch(API.details + '?student_id=' + encodeURIComponent(id), {
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (data.error) {
                        showToast('error', esc(data.error));
                        return false;
                    }

                    selectedStudentId = data.student.id;
                    engagedIntakeIds = data.engaged_intake_ids || [];

                    $('selectedStudentChip').innerHTML =
                        avatar(data.student.image, data.student.initials) +
                        '<span class="text-14 main-text fw-semibold mx-3">' + esc(data.student.name) + '</span>';

                    renderKeyValues('basicDetails', data.basic || []);

                    if ((data.advanced || []).length) {
                        renderKeyValues('advancedDetails', data.advanced);
                        $('advancedSection').classList.remove('d-none');
                    } else {
                        $('advancedSection').classList.add('d-none');
                    }

                    renderIntakes(data.intakes || []);
                    renderExistingDocuments(data.documents || []);
                    applyDocumentRules(data);

                    $('studentSections').classList.remove('d-none');
                    return true;
                } catch (e) {
                    showToast('error', 'Unable to load student details.');
                    return false;
                }
            }

            async function selectStudent(id) {
                hideSuggestions();
                $('studentSearchInput').value = '';
                resetRegistrationForm();
                await loadStudent(id);
            }

            window.clearSelectedStudent = function() {
                selectedStudentId = 0;
                engagedIntakeIds = [];
                requiredDocuments = [];
                $('studentSections').classList.add('d-none');
                resetRegistrationForm();
                $('studentSearchInput').focus();
            };

            /* ------------------------------ submit ------------------------------ */

            window.submitExistingStudentRegistration = async function(btn) {
                clearFieldErrors();

                if (!selectedStudentId) {
                    showToast('error', 'Please select a student first.');
                    return;
                }

                const intakeId = parseInt($('intake-selector').value, 10) || 0;
                if (intakeId <= 0) {
                    $('err-intake').textContent = 'Please select student intake';
                    $('intake-selector').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    return;
                }
                if (engagedIntakeIds.indexOf(intakeId) !== -1) {
                    $('err-intake').textContent = 'This student is already registered for the selected intake';
                    $('intake-selector').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    return;
                }
                if (!validateDocuments()) {
                    const firstErr = Array.from(document.querySelectorAll('.doc-feedback')).find((el) => el.innerHTML.trim() !== '');
                    if (firstErr) firstErr.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    return;
                }

                // selects / radios / textarea come from the form; files come from the document boxes
                const formData = new FormData($('registrationForm'));
                formData.append('student_id', selectedStudentId);
                appendStudentDocumentsToFormData(formData);

                const buttons = [$('submitRegistrationBtn'), $('submitRegistrationBtnTop')].filter(Boolean);
                const originals = buttons.map((b) => b.innerHTML);
                buttons.forEach((b) => {
                    b.disabled = true;
                    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                });

                try {
                    const res = await fetch(API.submit, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    const data = await res.json();

                    if (data.done) {
                        resetRegistrationForm();
                        await loadStudent(selectedStudentId); // refresh engaged intakes + documents on file
                        showToast('success', esc(data.done));
                        return;
                    }

                    const leftovers = [];
                    Object.keys(data).forEach((key) => {
                        const box = getBox(key);
                        const el = $('err-' + key);
                        if (box) {
                            box.querySelector('.doc-feedback').innerHTML = esc(data[key]);
                        } else if (el) {
                            el.textContent = data[key];
                        } else {
                            leftovers.push(esc(data[key]));
                        }
                    });
                    if (leftovers.length) showToast('error', leftovers.join('<br>'));
                } catch (e) {
                    showToast('error', 'Unable to save the registration. Please try again.');
                } finally {
                    buttons.forEach((b, i) => {
                        b.disabled = false;
                        b.innerHTML = originals[i];
                    });
                }
            };

            /* ------------------------------ wiring ------------------------------ */

            document.addEventListener('DOMContentLoaded', function() {
                const input = $('studentSearchInput');

                input.addEventListener('input', function() {
                    clearTimeout(searchTimer);
                    const q = input.value.trim();
                    if (q.length < 2) {
                        searchSeq++;
                        hideSuggestions();
                        $('studentSearchSpinner').classList.add('d-none');
                        return;
                    }
                    searchTimer = setTimeout(() => runSearch(q), 300);
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') hideSuggestions();
                });

                $('studentSuggestions').addEventListener('click', function(e) {
                    const item = e.target.closest('li[data-id]');
                    if (item) selectStudent(parseInt(item.dataset.id, 10));
                });

                document.addEventListener('click', function(e) {
                    if (!$('studentSearchWrapper').contains(e.target)) hideSuggestions();
                });
            });
        })();
    </script>
</body>

</html>