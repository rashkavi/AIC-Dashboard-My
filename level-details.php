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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['level-id']) && !empty($_GET['level-id']))) {
            header("Location: ./faculties");
        } else {
            $id = $_GET['level-id'];
            $level = Database::search("SELECT * FROM `levels` 
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
            WHERE `level_id` = '" . $id . "'");
            if ($level->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $levelData = $level->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Level Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                <div class="row p-0 m-0 g-0">
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
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12 col-md-8 col-lg-8">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><?= $levelData["level"] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="faculties" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $levelData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?rograme-id=<?= $levelData['programme_id'] ?>'><?= $levelData["programme"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intake-details?intake-id=<?= $levelData['intake_id'] ?>'><?= $levelData["intake"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $levelData["level"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-4 mt-3 mt-lg-0">
                                                        <div class="row gx-3 align-items-center justify-content-end h-100">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                            ?>
                                                                <div class="col-12 col-md-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="bg-transparent border-0 px-3" onclick="window.location.href='level-permissions?level-id=<?= $levelData['level_id'] ?>'">
                                                                        <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            <?php
                                                            }
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 2, $id)) {
                                                            ?>
                                                                <div class="col-12 col-md-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn px-5 pe-5 w-100" data-bs-target="#addSubject" data-bs-toggle="modal" data-i18n="add-new">Add New</button>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php
                                            $subjects = Database::search("SELECT * FROM `subjects` 
                                            LEFT JOIN `subject_group` ON `subjects`.`subject_group_subject_group_id`=`subject_group`.`subject_group_id`
                                            WHERE `subjects`.`levels_level_id`='" . $id . "'
                                           
                                            ORDER BY `subjects`.`subject_type_subject_type_id` ASC, `subjects`.`subject_group_subject_group_id` ASC");
                                            if ($subjects->num_rows > 0) {
                                                while ($subject = $subjects->fetch_assoc()) {
                                                    $labelClass = "";
                                                    $groupName = "";
                                                    if ($subject['subject_type_subject_type_id'] == 1) {
                                                        $labelClass = "label-role-9";
                                                        $groupName = "Core Subject";
                                                    } else {
                                                        $groupIndex = ((int)$subject['subject_group_subject_group_id'] % 9) + 2;
                                                        $labelClass = "label-role-" . $groupIndex;
                                                        $groupName = $subject['subject_group'];
                                                    }
                                            ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-10">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                <?php
                                                                if (!empty($subject['subject_cover']) && file_exists("./assets/imgs/subjects-covers/" . $subject['subject_cover'])) {
                                                                ?>
                                                                    <img src="./assets/imgs/subjects-covers/<?= $subject['subject_cover'] ?>" class="w-100 h-auto" alt="subject img">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="subject img">
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>

                                                            <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                    <?= htmlspecialchars($subject["subject"]) ?>
                                                                </h6>

                                                                <p class="text-14 main-text lh-lg mb-0">
                                                                    <?php
                                                                    if ($subject['subject_type_subject_type_id'] == 2) {
                                                                    ?>
                                                                        <span class="subject-label label-role-8">
                                                                            <iconify-icon icon="mdi:label-outline" width="15" height="15"></iconify-icon>
                                                                            Elective Subject
                                                                        </span>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <span class="subject-label <?= $labelClass ?>">
                                                                        <iconify-icon icon="<?= $subject['subject_type_subject_type_id'] == 1 ? 'mdi:book-open-page-variant' : 'pixelarticons:group' ?>" width="15" height="15"></iconify-icon>
                                                                        <?= $groupName ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                            <div class="col-12 col-md-3 p-0 m-0 position-relative">
                                                                <div class="row h-100 p-0 align-items-center justify-content-end">
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 10, $id)) {
                                                                    ?>
                                                                        <!-- Edit Button -->
                                                                        <div class="col-6 d-flex justify-content-end align-items-center m-0">
                                                                            <button class="custom-danger-btn w-100"
                                                                                data-i18n="edit"
                                                                                onclick="loadSubjectEditModel('<?= $subject['subject_id'] ?>','<?= $subject['subject'] ?>','<?= $subject['subject_cover'] ?>','<?= $subject['max_course_hours'] ?>','<?= $subject['subject_type_subject_type_id'] ?>','<?= $subject['subject_group_subject_group_id'] ?>')">
                                                                                Edit
                                                                            </button>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    $haveAnyPermission = false;

                                                                    if (UserPermissions::validPermisisonType('subject', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('lecture_schedule', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('pre_Recorded_lectures', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('lecture_recording', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('subject_students', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('subject_lecturers', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('exams', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('assignments', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('quiz', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }
                                                                    if (UserPermissions::validPermisisonType('subject_overview', $subject['subject_id'])) {
                                                                        $haveAnyPermission = true;
                                                                    }

                                                                    if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
                                                                        $lecturerSubject = Database::search("SELECT `lecturers_id` FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $id . "'");
                                                                        if ($lecturerSubject->num_rows > 0) {
                                                                            $haveAnyPermission = true;
                                                                        }
                                                                    }

                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission) {
                                                                    ?>
                                                                        <div class="col-6 d-flex justify-content-end align-items-center m-0">
                                                                            <button class="custom-btn w-100" type="button" data-i18n="view" onclick="location.href='subject-details?subject-id=<?= $subject['subject_id'] ?>'">
                                                                                View
                                                                            </button>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                            } else {
                                                ?>
                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row align-items-center justify-content-center">
                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                            ?>
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

                    <?php
                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 2, $id)) {
                    ?>
                        <!-- Subject Add Model => Start -->
                        <div class="modal fade br-0" id="addSubject" aria-hidden="true" aria-labelledby="addSubjectLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="addSubjectLabel">Add Subject</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="subject-name">
                                                    <span class="text-12 text-dark-red" id="as-name-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Max Subject Hours</label>
                                                    <input type="number" class="form-control custom-input mt-2" min="1" id="subject-hours">
                                                    <span class="text-12 text-dark-red" id="as-hours-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Type</label>
                                                    <select class="form-select custom-select mt-2" id="subject-type" onchange="displaySubjectGroup(this.value)">
                                                        <option value="">- Select Subject Type -</option>
                                                        <?php
                                                        $types = Database::search("SELECT * FROM `subject_type`");
                                                        while ($type = $types->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $type["subject_type_id"] ?>"><?= $type["subject_type"] ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span class="text-12 text-dark-red" id="as-type-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column d-none" id="subject-group-display-area">
                                                    <label for="" class="text-14 main-text">Subject Group</label>
                                                    <select class="form-select custom-select mt-2" id="subject-group">
                                                        <option value="0">- Select Subject group -</option>
                                                        <?php
                                                        $groups = Database::search("SELECT * FROM `subject_group` WHERE `levels_level_id` = '" . $id . "'");
                                                        while ($group = $groups->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $group["subject_group_id"] ?>"><?= $group["subject_group"] ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span class="text-12 text-dark-red" id="as-group-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 mt-4 py-1 d-flex flex-column d-none" id="subject-group-add-area">
                                                    <button class="custom-btn col-12 mt-lg-3" onclick="displayAddNewSubjectGroupInput();">Add New Subject Group</button>
                                                </div>

                                                <div class="col-12 col-lg-6 d-flex flex-column d-none" id="add-subject-group-input-area">
                                                    <label for="" class="text-14 main-text">New Subject Group</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="new-subject-group">
                                                    <span class="text-12 text-dark-red" id="as-group-feedback"></span>
                                                </div>

                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="Subject cover" id="subject-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 col-xl-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload your subject cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','subject-cover');">
                                                                        Upload Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 col-xl-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="custom-btn m-0 w-100" onclick="addSubject(this,<?= $id; ?>)">Save Subject</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="as-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Subject Add Model => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 10, $id)) {
                    ?>
                        <!-- Subject Edit Modal => Start -->
                        <div class="modal fade br-0" id="editSubject" aria-hidden="true" aria-labelledby="editSubjectLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editSubjectLabel">Edit Subject</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-subject-name">
                                                    <span class="text-12 text-dark-red" id="es-name-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Max Subject Hours</label>
                                                    <input type="number" class="form-control custom-input mt-2" min="1" id="edit-subject-hours">
                                                    <span class="text-12 text-dark-red" id="es-hours-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Type</label>
                                                    <select class="form-select custom-select mt-2" id="edit-subject-type" onchange="displaySubjectGroup(this.value)">
                                                        <?php
                                                        $types = Database::search("SELECT * FROM `subject_type`");
                                                        while ($type = $types->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $type["subject_type_id"] ?>"><?= $type["subject_type"] ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span class="text-12 text-dark-red" id="as-type-feedback"></span>
                                                </div>
                                                <div class="col-12 col-lg-6 d-flex flex-column d-none" id="edit-subject-group-display-area">
                                                    <label for="" class="text-14 main-text">Subject Group</label>
                                                    <select class="form-select custom-select mt-2" id="edit-subject-group">
                                                        <option value="0">- Select Subject group -</option>
                                                        <?php
                                                        $groups = Database::search("SELECT * FROM `subject_group` WHERE `levels_level_id` = '" . $id . "'");
                                                        while ($group = $groups->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $group["subject_group_id"] ?>"><?= $group["subject_group"] ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span class="text-12 text-dark-red" id="as-group-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Subject Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit subject cover" id="edit-subject-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                                            <div class="justify-content-end g-0 p-0 m-0 flex-grow-1 d-flex flex-column">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload subject cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-subject-cover');">
                                                                        Select another image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                        <!-- Hidden file input -->
                                                                        <input type="file" id="edit-cover-input" class="d-none" accept="image/*" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" id="save-edit">Save Changes</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="es-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Subject Edit Modal => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('level', 2, $id) || UserPermissions::hasPermission('level', 10, $id)) {
                    ?>
                        <!-- cover crop modal => Start -->
                        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Crop Image</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                                        <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                                    </div>
                                    <div class="modal-footer">
                                        <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- cover crop modal => End -->
                    <?php
                    }
                    ?>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
?>