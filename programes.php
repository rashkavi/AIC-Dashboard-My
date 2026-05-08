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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['faculty-id']) && !empty($_GET['faculty-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['faculty-id'];
            $faculty = Database::search("SELECT * FROM `faculties` WHERE `faculty_id` = '" . $id . "'");
            if ($faculty->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $facultyData = $faculty->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Programes - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row align-items-center p-0 m-0 g-0">
                                                    <div class="col-12 col-lg-8">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n="programes">Programes</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="faculties" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $facultyData["faculty"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                        <div class="row justify-content-end gx-3">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                            ?><div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="bg-transparent border-0 px-3" onclick="window.location.href='faculty-permissions.php?faculty-id=<?= $id; ?>'">
                                                                        <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>

                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 2, $id)) {
                                                            ?> <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" data-bs-target="#addProgramme" data-bs-toggle="modal" data-i18n="add-new">Add New</button>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php
                                            $query = "
                                            SELECT 
                                                p.*, 
                                                COUNT(i.intake_id) AS total_intakes
                                            FROM programmes p
                                            LEFT JOIN intakes i 
                                                ON i.programmes_programme_id = p.programme_id
                                            WHERE p.faculties_faculty_id = '" . $id . "'
                                            GROUP BY p.programme_id
                                        ";

                                            $programmes = Database::search($query);

                                            if ($programmes->num_rows > 0) {
                                                while ($programme = $programmes->fetch_assoc()) {
                                            ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-10">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                <?php
                                                                $programme_cover = "./assets/imgs/programmes-covers/" . $programme['programme_cover'];
                                                                if (!empty($programme['programme_cover']) && file_exists($programme_cover)) {
                                                                ?>
                                                                    <img src="./assets/imgs/programmes-covers/<?= $programme['programme_cover'] ?>" class="w-100 h-auto" alt="programme img">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="programme img">
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                    <?= htmlspecialchars($programme['programme']) ?>
                                                                </h6>
                                                                <p class="text-14 main-text lh-lg mb-0">
                                                                    Total Intakes in Programe : <?= htmlspecialchars($programme['total_intakes']) ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                                <div class="row gx-3 h-100 align-content-center">
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <?php
                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 10, $id)) {
                                                                        ?>
                                                                            <button class="custom-danger-btn w-100" data-i18n="edit" onclick="loadProgrammeEditModel('<?= $programme['programme_id'] ?>','<?= $programme['programme'] ?>','<?= $programme['programme_cover'] ?>','<?= $programme['programme_duration_programme_duration_id'] ?>')">Edit</button>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="view" onclick="window.location.href='intakes?programe-id=<?= $programme['programme_id']; ?>'">View</button>
                                                                    </div>
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

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('department', 2, $id)) {
                    ?>
                        <!-- Programme Add Model => Start -->
                        <div class="modal fade br-0" id="addProgramme" aria-hidden="true" aria-labelledby="addProgrammeLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editProgrammeLabel">Add Programe</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Programe Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="programme-name">
                                                    <span class="text-12 text-dark-red" id="ap-name-feedback"></span>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center" id="programme-duration-row">
                                                        <div class="col-12 col-lg-6 d-flex flex-column" id="select-duration">
                                                            <label for="" class="text-14 main-text">Programe Duration</label>
                                                            <select name="" class="form-select custom-select mt-2" id="programme-duration">
                                                                <option value="0">Select Duration</option>
                                                                <?php
                                                                $durations = Database::search("SELECT * FROM `programme_duration` ORDER BY `order_by` ASC");
                                                                while ($duration = $durations->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $duration['programme_duration_id'] ?>"><?php echo $duration['programme_duration'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="ap-duration-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn m-0 w-100" onclick="addNewProgrammeDuration();">Add New Duration</button>
                                                        </div>
                                                    </div>
                                                    <div class="row p-0 gy-3 gx-3 align-content-center d-none" id="new-programme-duration-row">
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label class="text-14 main-text d-inline-flex align-items-center" for="">
                                                                New Programme Duration
                                                                <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="Add the duration for the new programme you want to create.">
                                                                    <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                </span>
                                                            </label>
                                                            <input type="text" class="form-control custom-input mt-2" id="new-programme-duration">
                                                            <span class="text-12 text-dark-red" id="ap-new-duration-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn m-0 w-100" onclick="selectExistingProgrameDuration()">Select Existing Duration</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Programe Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="Programme cover" id="programme-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select programe cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','programme-cover');">
                                                                        Select Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addProgramme(this,<?php echo $id; ?>)">Save Programme</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="ap-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Programme Add Model => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 10, $id)) {
                    ?>
                        <!-- Programme Edit Modal => Start -->
                        <div class="modal fade br-0" id="editProgramme" aria-hidden="true" aria-labelledby="editProgrammeLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editProgrammeLabel">Edit Programe</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Programe Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" value="Portal Payment" id="edit-programme-name">
                                                    <span class="text-12 text-dark-red" id="ep-name-feedback"></span>
                                                </div>
                                                <div class="col-12">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center" id="edit-programme-duration-row">
                                                        <div class="col-12 col-lg-6 d-flex flex-column" id="edit-select-duration">
                                                            <label for="" class="text-14 main-text">Programe Duration</label>
                                                            <select name="" class="form-select custom-select mt-2" id="edit-programme-duration">
                                                                <option value="0">Select Duration</option>
                                                                <?php
                                                                $durations = Database::search("SELECT * FROM `programme_duration` ORDER BY `order_by` ASC");
                                                                while ($duration = $durations->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $duration['programme_duration_id'] ?>"><?php echo $duration['programme_duration'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="ep-duration-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn m-0 w-100" onclick="addNewEditProgrammeDuration();">Add New Duration</button>
                                                        </div>
                                                    </div>
                                                    <div class="row p-0 gy-3 gx-3 align-content-center d-none" id="edit-new-programme-duration-row">
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label class="text-14 main-text d-inline-flex align-items-center" for="">
                                                                New Programme Duration
                                                                <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="Add the duration for the new programme you want to create.">
                                                                    <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                </span>
                                                            </label>
                                                            <input type="text" class="form-control custom-input mt-2" id="new-edit-programme-duration">
                                                            <span class="text-12 text-dark-red" id="ep-new-duration-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn m-0 w-100" id="existing-edit-programme-duration">Select Existing Duration</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Programe Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit-programme-cover" id="edit-programme-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex">
                                                            <div class="justify-content-center g-0 p-0 m-0 flex-grow-1 d-flex flex-column">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select programe cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn col-12 d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-programme-cover');">
                                                                        Select Another Image
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
                                                    <span class="text-12 text-dark-red" id="ep-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Payment Edit Modal => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 2, $id) || UserPermissions::hasPermission('faculty', 10, $id)) {
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
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php

            }
        }
    }
}
?>