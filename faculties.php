<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['branch-id']) && !empty($_GET['branch-id']))) {
            header("Location: ./branches");
        } else {

            $id = $_GET['branch-id'];
            $branch = Database::search("SELECT * FROM `branches` WHERE `branch_id` = '" . $id . "'");
            if ($branch->num_rows == 0) {
                header("Location: ./branches");
            } else {
                $branchData = $branch->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Faculties - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row align-items-center p-0 m-0 g-0">
                                                    <div class="col-12 col-lg-8">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n="faculties">Faculties</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="branches" href='./branches'>Branches</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $branchData["branch"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                        <div class="row gx-3 justify-content-end">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('branch', 1, $branchData['branch_id']) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                            ?><div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="bg-transparent border-0 px-3" onclick="window.location.href='branch-permissions?branch-id=<?= $branchData['branch_id'] ?>'">
                                                                        <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            <?php
                                                            }

                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branch', 2, $branchData['branch_id'])) {
                                                            ?>
                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn col-12" data-bs-target="#addfaculty" data-bs-toggle="modal" data-i18n="add-new">Add New</button>
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
                                        f.*, 
                                        COUNT(p.programme_id) AS total_programmes
                                    FROM faculties f
                                    LEFT JOIN programmes p 
                                        ON p.faculties_faculty_id = f.faculty_id
                                    WHERE f.branches_branch_id = '" . $branchData['branch_id'] . "'
                                    GROUP BY f.faculty_id
                                    ";

                                            $fuculties = Database::search($query);

                                            if ($fuculties->num_rows > 0) {
                                                while ($faculty = $fuculties->fetch_assoc()) {
                                            ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-10">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                <?php
                                                                $faculty_cover = "./assets/imgs/faculties-covers/" . $faculty['faculty_cover'];
                                                                if (!empty($faculty['faculty_cover']) && file_exists($faculty_cover)) {
                                                                ?>
                                                                    <img src="./assets/imgs/faculties-covers/<?= $faculty['faculty_cover'] ?>" class="w-100 h-auto" alt="faculty img">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="faculty img">
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                    <?= htmlspecialchars($faculty['faculty']) ?>
                                                                </h6>
                                                                <p class="text-14 main-text lh-lg mb-0">
                                                                    Number of programmes: <?= htmlspecialchars($faculty['total_programmes']) ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                                <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview', 0)) {
                                                                        if ($faculty['total_programmes'] > 0) {
                                                                    ?>
                                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <button class="custom-danger-btn w-100" data-i18n="edit" onclick="loadFacultyEditModel('<?= $faculty['faculty_id'] ?>','<?= $faculty['faculty'] ?>','<?= $faculty['faculty_cover'] ?>')">Edit</button>
                                                                            </div>
                                                                        <?php

                                                                        } else {
                                                                        ?>
                                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <div class="dropdown w-100">
                                                                                    <button
                                                                                        class="custom-danger-btn w-100 dropdown-toggle d-flex align-items-center justify-content-center gap-2"
                                                                                        type="button"
                                                                                        data-bs-toggle="dropdown"
                                                                                        aria-expanded="false">
                                                                                        Actions
                                                                                    </button>
                                                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                                                                        <li>
                                                                                            <button
                                                                                                class="dropdown-item d-flex align-items-center gap-2"
                                                                                                onclick="loadFacultyEditModel('<?= $faculty['faculty_id'] ?>','<?= $faculty['faculty'] ?>','<?= $faculty['faculty_cover'] ?>')">
                                                                                                <iconify-icon icon="line-md:edit-filled" width="18" height="18"></iconify-icon>
                                                                                                Edit
                                                                                            </button>
                                                                                        </li>
                                                                                        <li>
                                                                                            <hr class="dropdown-divider">
                                                                                        </li>
                                                                                        <li>
                                                                                            <button
                                                                                                class="dropdown-item d-flex align-items-center gap-2 text-danger"
                                                                                                onclick="confirmDeleteFaculty('<?= $faculty['faculty_id'] ?>')">
                                                                                                <iconify-icon icon="material-symbols:delete-outline-rounded" width="18" height="18"></iconify-icon>
                                                                                                Delete
                                                                                            </button>
                                                                                        </li>
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                    <?php
                                                                        }
                                                                    }

                                                                    ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" data-i18n="view" onclick="window.location.href='programes.php?faculty-id=<?= $faculty['faculty_id']; ?>'">View</button>
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
                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branch', 2, $branchData['branch_id'])) {
                    ?>
                        <!-- faculty Add Model => Start -->
                        <div class="modal fade br-0" id="addfaculty" aria-hidden="true" aria-labelledby="addfacultyLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editfacultyLabel" data-i18n="add-faculty">Add faculty</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Faculty Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="faculty-name">
                                                    <span class="text-12 text-dark-red" id="af-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Faculty Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="faculty cover" id="faculty-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex mt-0 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select faculty cover here.
                                                                        </br>
                                                                        Important guidelines:
                                                                        </br>
                                                                        Image size must be 2MB or less,
                                                                        </br>
                                                                        with a 16:9 aspect ratio for optimal quality.
                                                                        </br>
                                                                        Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','faculty-cover');">
                                                                        Select Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addFaculty(this,<?= $id ?>)">Save faculty</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="af-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- faculty Add Model => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branch', 10, $branchData['branch_id'])) {
                    ?>
                        <!-- faculty Edit Modal => Start -->
                        <div class="modal fade br-0" id="editFaculty" aria-hidden="true" aria-labelledby="editFacultyLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editFacultyLabel">Edit Faculty</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Faculty Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" value="Portal Payment" id="edit-faculty-name">
                                                    <span class="text-12 text-dark-red" id="ef-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Faculty Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit-Faculty-cover" id="edit-faculty-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select faculty cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-faculty-cover');">
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
                                                    <span class="text-12 text-dark-red" id="ef-cover-feedback"></span>
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

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branch', 2, $branchData['branch_id']) || UserPermissions::hasPermission('branch', 10, $branchData['branch_id'])) {
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
                    <?php
                    }
                    ?>

                    <div class="modal fade" id="removeFacultyConfirmModal" tabindex="-1"
                        aria-labelledby="removeFacultyConfirmModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">

                                <!-- Close button -->
                                <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                    <button type="button"
                                        class="btn-close text-end"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <div class="modal-body text-center p-4">

                                    <!-- Warning Icon -->
                                    <div class="mb-3">
                                        <iconify-icon
                                            icon="bx:error-circle"
                                            width="48"
                                            height="48"
                                            class="text-danger">
                                        </iconify-icon>
                                    </div>

                                    <!-- Title -->
                                    <h5 class="fw-semibold mb-2" id="removeFacultyConfirmModalTitle">
                                        Confirm Faculty Removal
                                    </h5>

                                    <!-- Main message -->
                                    <p class="text-muted mb-3">
                                        You are about to remove this <strong>Faculty</strong>.
                                    </p>

                                    <!-- Detailed impact explanation -->
                                    <div class="alert alert-warning text-start small mb-4">
                                        <div class="d-flex align-items-start gap-2">
                                            <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                            <div>
                                                <strong>This action will:</strong>
                                                <ul class="mb-0 ps-3">
                                                    <li>Permanently remove the selected faculty</li>
                                                    <li>This action <strong>cannot be undone</strong></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action buttons -->
                                    <div class="d-flex justify-content-center gap-3">
                                        <button type="button"
                                            class="btn btn-outline-secondary px-4 br-0"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </button>

                                        <button type="button"
                                            id="confirmRemoveFaculty"
                                            class="btn btn-danger px-4 br-0">
                                            <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                            Remove Faculty
                                        </button>
                                    </div>

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
<?php
            }
        }
    }
}
?>