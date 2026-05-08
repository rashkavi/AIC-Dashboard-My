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
            header("Location: ./faculties");
        } else {

            $id = $_GET['subject-id'];
            $subject = Database::search("SELECT * FROM `subjects`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id` 
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
            WHERE `subject_id` = '" . $id . "'");
            if ($subject->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $subjectData = $subject->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $haveAnyPermission = false;
                $loginLecturer = false;

                if (UserPermissions::validPermisisonType('subject', $id)) {
                    $haveAnyPermission = true;
                }

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission)) {
                    header("Location: ./level-details?level-id=" . $subjectData['level_id']);
                } else {

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Subject Attachments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                    <div class="row p-0 m-0 g-0 ">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Course Overview");
                                            ?>
                                        </div>

                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include './includes/components/skeleton-header.php';
                                            include './includes/components/skeleton-courses.php'; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8 col-lg-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $subjectData["subject"] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?id=<?= $subjectData['faculties_faculty_id'] ?>'><?= $subjectData["faculty"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programme-id=<?= $subjectData['programme_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intake-details?intake-id=<?= $subjectData['intake_id'] ?>'><?= $subjectData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./level-details?level-id=<?= $subjectData['level_id'] ?>'><?= $subjectData["level"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $subjectData["subject"] ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subjects', 1, $id)) {
                                                        ?>
                                                            <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn col-12" data-bs-target="#addTute" data-bs-toggle="modal">Add Tute</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 m-0">
                                                    <div class="row g-4 m-0">
                                                        <?php
                                                        $attachment = Database::search("SELECT * FROM `subject_has_attachments` WHERE `subjects_subject_id` = '" . $id . "'");
                                                        if ($attachment->num_rows > 0) {
                                                            $iconMap = [
                                                                "pdf" => "pdf.png",
                                                                "doc" => "doc.png",
                                                                "docx" => "doc.png",
                                                                "zip" => "zip.png",
                                                                "rar" => "zip.png",
                                                                "ppt" => "ppt.png",
                                                                "pptx" => "ppt.png",
                                                                "jpg" => "image.png",
                                                                "jpeg" => "image.png",
                                                                "png" => "image.png",
                                                                "gif" => "image.png",
                                                                "bmp" => "image.png",
                                                                "webp" => "image.png",
                                                                "svg" => "image.png"
                                                            ];
                                                            while ($attachmentData = $attachment->fetch_assoc()) {
                                                                if (!empty($attachmentData["attachment"]) && file_exists("./assets/uploads/subject-attachments/" . $attachmentData["attachment"])) {
                                                                    $fileName = $attachmentData['attachment'];
                                                                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                                                    $icon = isset($iconMap[$extension]) ? $iconMap[$extension] : "file.png";
                                                        ?>
                                                                    <div class="col-12 col-lg-4 file-item cursor-pointer" data-attachment-id="<?= $attachmentData['subject_has_attachment_id'] ?>">
                                                                        <div class="bl-solid component-theme">
                                                                            <div class="row p-0">
                                                                                <div class="col-10" onclick="viewSubjectAttachment('<?php echo $attachmentData['attachment'] ?>', '<?php echo $attachmentData['attachment_name'] ?>')">
                                                                                    <div class="row p-3">
                                                                                        <div class="col-3 d-flex align-items-center">
                                                                                            <img src="assets/imgs/icons/<?= $icon ?>" class="file-icon" alt="">
                                                                                        </div>
                                                                                        <div class="col-8">
                                                                                            <div class="file-name">
                                                                                                <div class="name"><?php echo $attachmentData["attachment_name"] ?></div>
                                                                                            </div>
                                                                                            <div class="file-name">
                                                                                                <div class="text-12 sub-text">Upload At : <?php echo date("Y-m-d h:i A", strtotime($attachmentData["date_added"])) ?></div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-2">
                                                                                    <!-- Container -->
                                                                                    <div class="d-flex align-items-center justify-content-center p-0 m-0 table-btn-danger h-100 w-100"
                                                                                        onclick="removeSubjectAttachment(this,<?= $attachmentData['subject_has_attachment_id'] ?>)">
                                                                                        <iconify-icon icon="pajamas:remove-all" width="20" height="20"
                                                                                            class="text-danger p-0 m-0 text-center">
                                                                                        </iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                }
                                                            }
                                                        } else {
                                                            ?>
                                                            <div class="col-12 px-4 py-3 component-theme h-100">
                                                                <div class="row align-items-center justify-content-center h-100">
                                                                    <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                </div>
                                                            </div>
                                                        <?php
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

                        <div class="modal fade br-0" id="addTute" aria-hidden="true" aria-labelledby="addTuteLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editfacultyLabel">Add Subject Attachments</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 d-flex flex-column">
                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                <span>Drag & Drop your files here</span>
                                                <span>OR</span>
                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                <input type="file" class="d-none" id="file-selector-input" multiple
                                                    accept=".pdf,.doc,.docx,.zip,.rar,.ppt,.pptx,image/*">
                                                <div class="col-12 px-1 mt-4">
                                                    <div class="row" id="list-section">
                                                    </div>
                                                </div>
                                            </div>
                                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                        </div>
                                        <div class="col-12 d-flex align-items-md-end justify-content-end mt-4">
                                            <button class="px-5 custom-btn m-0" onclick="addSubjectAttachments(this,<?= $id ?>)">Save Attachments</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>