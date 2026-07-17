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
        if (!(SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
            header("Location: ./students");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Upload Students Bulk - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                            load_side_menu::load("Students");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Students");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Upload Student Bulk</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students'>Students</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">Upload student bulk</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 component-theme p-4">
                                            <div class="row p-0 m-0">
                                                <div class="col-12 bl-solid p-0 m-0 d-flex flex-column">
                                                    <div class="p-2 d-flex flex-column align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                        <span>Drag & Drop your files here</span>
                                                        <span>OR</span>
                                                        <div class="col-12 mt-3">
                                                            <div class="row gap-3 align-items-center justify-content-center">
                                                                <button class="custom-btn px-4 col-4" onclick="downloadCSV()">Download CSV Format</button>
                                                                <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                            </div>
                                                        </div>

                                                        <input type="file" class="d-none" id="file-selector-input" accept=".csv">

                                                    </div>
                                                    <div class="col-12 p-4 d-none" id="list-section-container">
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <div class="row" id="list-section">

                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-2">
                                                                        <button class="custom-btn w-100" type="button" onclick="uploadStudentBulk(this);">
                                                                            Upload Bulk
                                                                        </button>
                                                                    </div>

                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red p-2" id="csv-file-feedback"></span>
                                                </div>

                                                <div class="container p-0 mt-4">
                                                    <div class="bulk-instructions-header">
                                                        <h4 class="sub-topic-color">Student Bulk Upload Instructions</h4>
                                                        <p class="bulk-instructions-subtitle">Follow these guidelines to ensure successful student data import</p>
                                                    </div>

                                                    <div class="bulk-instructions-note p-3">
                                                        <div class="bulk-instructions-note-title text-16 fw-semibold mb-1">
                                                            Important Upload Guidelines
                                                        </div>

                                                        <p class="text-14 mt-1">
                                                            <strong class="text-danger">All fields must be completed accurately.</strong></br>
                                                            Ensure your CSV file adheres to the required field structure, value types, and date formats provided in the instructions.
                                                        </p>

                                                        <p class="text-14 mt-2">
                                                            During validation, the system will automatically detect missing information, invalid formats, and improperly structured records.
                                                            Rows containing errors will not be processed, so please review your file thoroughly before uploading.
                                                        </p>
                                                    </div>
                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Text Field Requirements</h2>
                                                        <p class="bulk-instructions-section-content">Please follow the character limits when entering student names:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li><strong>First Name:</strong> Maximum 45 characters</li>
                                                            <li><strong>Last Name:</strong> Maximum 45 characters</li>
                                                            <li><strong>Full Name:</strong> Maximum 100 characters</li>
                                                            <li><strong>Name With Initials:</strong> Maximum 45 characters</li>
                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - Only alphabetic characters, spaces, hyphens (-), and apostrophes (') are recommended.<br>
                                                            - Avoid numbers, special symbols, or unnecessary punctuation.<br>
                                                            - Exceeding character limits will generate a row-level validation error.
                                                        </p>
                                                    </div>

                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Reference IDs</h2>
                                                        <p class="bulk-instructions-section-content">Use the correct numeric IDs for titles,genders and marital statuses:</p>

                                                        <div class="bulk-instructions-grid-container">
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Title IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $titles = Database::search("SELECT * FROM `title` ORDER BY `title_id` ASC");
                                                                    while ($title = $titles->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $title["title_id"]; ?> - <?= $title["title"]; ?></li>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </ul>
                                                            </div>

                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Gender IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $genders = Database::search("SELECT * FROM `gender` ORDER BY `gender_id` ASC");
                                                                    while ($gender = $genders->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $gender["gender_id"]; ?> - <?= $gender["gender"]; ?></li>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </ul>
                                                            </div>
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Marital Status IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $status = Database::search("SELECT * FROM `marital_status` ORDER BY `marital_status_id` ASC");
                                                                    while ($marital_status = $status->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $marital_status["marital_status_id"]; ?> - <?= $marital_status["marital_status"]; ?></li>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                        <p class="bulk-instructions-section-content text-danger mt-2">
                                                            <strong>Notes:</strong><br>
                                                            - Only enter the numeric ID values in the CSV file (e.g., <em>1</em> for Male, <em>2</em> for Female).<br>
                                                            - The displayed labels (Mr, Ms, Married, etc.) are for reference only and should NOT be typed into the CSV.<br>
                                                            - If an invalid or non-existing ID is used, the system will reject that row during validation.<br>
                                                            - Ensure that Title ID, Gender ID, and Marital Status ID are not left empty (unless specified as optional in your CSV template).<br>
                                                        </p>
                                                    </div>

                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Date of Birth Formats</h2>
                                                        <p class="bulk-instructions-section-content">The following date formats are accepted (DD/MM/YYYY or YYYY/MM/DD variations):</p>

                                                        <div class="bulk-instructions-grid-container">
                                                            <div class="bulk-instructions-grid-item">
                                                                <ul class="bulk-instructions-list">
                                                                    <li><strong>2025/11/02</strong> (YYYY/MM/DD)</li>
                                                                    <li><strong>2025/11/2</strong> (YYYY/M/D)</li>
                                                                    <li><strong>02/11/2025</strong> (DD/MM/YYYY)</li>
                                                                    <li><strong>2/11/2025</strong> (D/M/YYYY)</li>
                                                                    <li><strong>24/10/2025</strong> (DD/MM/YYYY)</li>
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <p class="bulk-instructions-section-content text-danger mt-2">
                                                            <strong>Notes:</strong><br>
                                                            - Use a slash (<strong>/</strong>) as the separator.<br>
                                                            - Month and day may be 1 or 2 digits.<br>
                                                            - Year must always be 4 digits.<br>
                                                            - Invalid or ambiguous formats will cause a row-level upload error.
                                                        </p>
                                                    </div>
                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">National ID (NIC) Requirements</h2>
                                                        <p class="bulk-instructions-section-content">Allowed Sri Lankan NIC formats:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li>
                                                                <strong>Old NIC Format:</strong>
                                                                - 9 digits followed by <strong>V</strong> or <strong>v</strong>
                                                                - Example: <strong>982547963V</strong>
                                                            </li>

                                                            <li>
                                                                <strong>New NIC Format:</strong>
                                                                - 12 digits (only numbers)
                                                                - Example: <strong>200145302789</strong>
                                                            </li>
                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - No other characters are allowed (e.g., spaces, hyphens, dots).<br>
                                                            - NIC field is required — cannot be left empty.<br>
                                                            - If NIC format is incorrect, the upload will fail for that row.
                                                        </p>
                                                    </div>
                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Email Address Requirements</h2>
                                                        <p class="bulk-instructions-section-content">
                                                            Please ensure that all student and guardian email addresses follow the correct format. Invalid email formats will cause row-level validation errors during bulk upload.
                                                        </p>

                                                        <ul class="bulk-instructions-list">
                                                            <li>Email must contain an <strong>@</strong> symbol (e.g., example@gmail.com)</li>
                                                            <li>Email must include a valid domain name (e.g., gmail.com, yahoo.com, outlook.com)</li>
                                                            <li>No spaces or illegal characters allowed</li>
                                                            <li>Maximum length: <strong>100 characters</strong></li>
                                                        </ul>


                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - Email address must be unique for each student.<br>
                                                            - Exceeding character limits or using invalid characters will generate a row-level validation error.
                                                        </p>
                                                    </div>

                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Mobile Number & WhatsApp Requirements</h2>
                                                        <p class="bulk-instructions-section-content">Allowed phone number formats:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li><strong>Sri Lankan Mobile Format:</strong> Must start with <strong>07</strong> and contain <strong>10 digits</strong>
                                                                <br>Example: 0712345678
                                                            </li>

                                                            <li><strong>International Format (without +):</strong> 8–15 digits, starts with 1–9
                                                                <br>Example: 769679778 → will be converted automatically to <strong>0769679778</strong>
                                                            </li>

                                                            <li><strong>International Format (with +):</strong> + followed by country code and number
                                                                <br>Example: +94712345678
                                                            </li>

                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger"><strong>Notes:</strong><br>
                                                            - Only mobile numbers are allowed — no landline numbers.<br>
                                                            - Minimum digits: <strong>8</strong>, Maximum digits: <strong>15</strong>.<br>
                                                            - Mobile number & WhatsApp must be unique for each student.
                                                        </p>
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
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/bulkfileChooser.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php

        }
    }
} ?>