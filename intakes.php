<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['programe-id']) && !empty($_GET['programe-id']))) {
            header("Location: ./programes");
        } else {
            $id = $_GET['programe-id'];
            $programme = Database::search("SELECT * FROM `programmes` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `programme_id` = '" . $id . "'");
            if ($programme->num_rows == 0) {
                header("Location: ./programes");
            } else {
                $programmeData = $programme->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $query = "
                                            SELECT 
                                                i.*, 
                                                COUNT(s.intakes_intake_id) AS active_students
                                            FROM intakes i
                                            LEFT JOIN students_has_intakes s 
                                                ON s.intakes_intake_id = i.intake_id
                                                AND s.status_status_id = '1'
                                            WHERE i.programmes_programme_id = '" . $id . "'
                                            GROUP BY i.intake_id
                                        ";

                $intakes = Database::search($query);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Intakes - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden">
                    <ul class="notifications"></ul>
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
                                                            <span class="main-topic lh-lg" data-i18n="intakes">Intakes</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="faculties" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?php echo $programmeData['faculty_id'] ?>'><?php echo $programmeData["faculty"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo $programmeData["programme"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                        <div class="row gx-3 justify-content-end">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                            ?>
                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="bg-transparent border-0 px-3" onclick="window.location.href='programe-permissions.php?programme-id=<?= $id; ?>'">
                                                                        <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <?php
                                                            }
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 2, $id)) {
                                                                if ($intakes->num_rows > 0) {
                                                                ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <!-- <button class="custom-btn w-100" data-bs-target="#addIntake" data-bs-toggle="modal" data-i18n="add-new">Add New</button> -->
                                                                        <div class="dropdown w-100">
                                                                            <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                                Action
                                                                            </button>
                                                                            <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                <li><a class="dropdown-item" data-bs-target="#addIntake" data-bs-toggle="modal">Add New Intake</a></li>
                                                                                <li><a class="dropdown-item" data-bs-target="#duplicateIntake" data-bs-toggle="modal">Duplicate Intake</a></li>
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-bs-target="#addIntake" data-bs-toggle="modal">
                                                                            Add New Intake
                                                                        </button>
                                                                    </div>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php

                                            if ($intakes->num_rows > 0) {
                                                while ($intake = $intakes->fetch_assoc()) {
                                            ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-10">
                                                        <div class="row p-0 m-0 g-0 align-content-center" id="intake-row" data-intake-id="<?= $intake['intake_id'] ?>">
                                                            <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                <?php
                                                                $intake_cover = "./assets/imgs/intakes-covers/" . $intake['intake_cover'];
                                                                if (!empty($intake['intake_cover']) && file_exists($intake_cover)) {
                                                                ?>
                                                                    <img src="./assets/imgs/intakes-covers/<?php echo $intake['intake_cover'] ?>" class="w-100 h-auto" alt="intake img">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="intake img">
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                    <?= htmlspecialchars($intake['intake']) ?>
                                                                </h6>
                                                                <p class="text-14 main-text lh-lg mb-0">
                                                                    Total Active Students in Intake : <?= htmlspecialchars($intake['active_students']) ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                                <div class="row gx-3 h-100 align-content-center">
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <?php
                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 10, $id)) {
                                                                        ?>
                                                                            <button class="custom-danger-btn w-100" data-i18n="edit" onclick="loadIntakeEditModel('<?= $intake['intake_id'] ?>','<?= $intake['intake'] ?>','<?= $intake['intake_cover'] ?>')">Edit</button>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="view" onclick="window.location.href='intake-details?intake-id=<?= $intake['intake_id']; ?>'">View</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
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
                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 2, $id)) {
                    ?>
                        <!-- intake Add Model => Start -->
                        <div class="modal fade br-0" id="addIntake" aria-hidden="true" aria-labelledby="addIntakeLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="addIntakeLabel">Add Intake</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Intake Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="intake-name">
                                                    <span class="text-12 text-dark-red" id="ai-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Intake Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="intake cover" id="intake-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select intake cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','intake-cover');">
                                                                        Select Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addIntake(this,<?php echo $id; ?>)">Save intake</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="ai-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- intake Add Model => End -->

                        <div class="modal fade br-0" id="duplicateIntake" aria-hidden="true" aria-labelledby="duplicateIntakeLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="duplicateIntakeLabel">Duplicate Intake</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Select Branch</label>
                                                    <select name="" id="branch-selector" class="form-select custom-select mt-2" onchange="loadFaculties();">
                                                        <option value="0" selected>- Select -</option>
                                                        <?php
                                                        $query = "SELECT * FROM `branches`";

                                                        $branches = Database::search($query);
                                                        while ($data = $branches->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?php echo $data["branch_id"]; ?>"><?php echo $data["branch"]; ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="branch-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Select Faculty</label>
                                                    <select name="" id="faculty-selector" disabled class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                        <option value="0" selected>- Select -</option>

                                                    </select>
                                                    <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Select Programmes</label>
                                                    <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                        <option value="0" selected>- Select -</option>
                                                    </select>
                                                    <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Select Intake</label>
                                                    <select name="" id="intake-selector" disabled class="form-select custom-select mt-2">
                                                        <option value="0" selected>- Select -</option>
                                                    </select>
                                                    <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Intake Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="duplicate-name">
                                                    <span class="text-12 text-dark-red" id="duplicate-name-feedback"></span>
                                                </div>

                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Intake Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="intake cover" id="duplicate-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select intake cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('duplicate-cover-input','duplicate-cover');">
                                                                        Select Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="duplicate-cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="duplicateIntake(this,<?= $id ?>)">Duplicate batch</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="duplicate-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 10, $id)) {
                    ?>
                        <!-- intake Edit Modal => Start -->
                        <div class="modal fade br-0" id="editIntake" aria-hidden="true" aria-labelledby="editIntakeLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editIntakeLabel">Edit Intake</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Intake Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" value="Portal Payment" id="edit-intake-name">
                                                    <span class="text-12 text-dark-red" id="ei-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column justify-content-end">
                                                    <label for="" class="text-14 main-text">Intake Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit-intake-cover" id="edit-intake-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex">
                                                            <div class="justify-content-center g-0 p-0 m-0 flex-grow-1 d-flex flex-column">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select intake cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-intake-cover');">
                                                                        Select another Image
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
                                                    <span class="text-12 text-dark-red" id="ei-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- intake Edit Modal => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('programme', 2, $id) || UserPermissions::hasPermission('programme', 2, $id)) {
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