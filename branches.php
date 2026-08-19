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

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Branches - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                    <span class="main-topic lh-lg" data-i18n="">Branches</span>
                                                    <span class="text-14 sub-text-color lh-base mt-1">Effortlessly manage branch, track activities, and enhance collaboration.</span>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 1) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?><div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="bg-transparent border-0 px-3" onclick="window.location.href='branches-permissions'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }

                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 2)) {
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
                                         b.*, 
                                         COUNT(f.faculty_id) AS total_faculties
                                     FROM branches b
                                     LEFT JOIN faculties f
                                         ON f.branches_branch_id = b.branch_id
                                     GROUP BY b.branch_id
                                     ";

                                    $branches = Database::search($query);

                                    if ($branches->num_rows > 0) {
                                        while ($branch = $branches->fetch_assoc()) {
                                    ?>
                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                        <?php
                                                        $branch_cover = "./assets/imgs/branches-covers/" . $branch['branch_cover'];
                                                        if (!empty($branch['branch_cover']) && file_exists($branch_cover)) {
                                                        ?>
                                                            <img src="./assets/imgs/branches-covers/<?= $branch['branch_cover'] ?>" class="w-100 h-auto" alt="branch img">
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="branch img">
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                        <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                            <?= htmlspecialchars($branch['branch']) ?>
                                                        </h6>
                                                        <p class="text-14 main-text lh-lg mb-0">
                                                            Number of faculties: <?= htmlspecialchars($branch['total_faculties']) ?>
                                                        </p>
                                                    </div>

                                                    <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 10)) {
                                                                if ($branch['total_faculties'] > 0) {
                                                            ?>
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-danger-btn w-100" onclick="loadBranchEditModel('<?= $branch['branch_id'] ?>','<?= $branch['branch'] ?>','<?= $branch['branch_cover'] ?>')">Edit</button>
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
                                                                                        onclick="loadBranchEditModel('<?= $branch['branch_id'] ?>','<?= $branch['branch'] ?>','<?= $branch['branch_cover'] ?>')">
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
                                                                                        onclick="confirmDeleteBranch('<?= $branch['branch_id'] ?>')">
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
                                                                <button class="custom-btn w-100" onclick="window.location.href='faculties.php?branch-id=<?= $branch['branch_id']; ?>'">View</button>
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
            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 2)) {
            ?>
                <div class="modal fade br-0" id="addfaculty" aria-hidden="true" aria-labelledby="addfacultyLabel" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 darkColor" id="editfacultyLabel">Add branch</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Branch Name</label>
                                            <input type="text" class="form-control custom-input mt-2" id="branch-name">
                                            <span class="text-12 text-dark-red" id="af-name-feedback"></span>
                                        </div>
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text">Branch Cover</label>
                                            <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="branch cover" id="branch-cover">
                                                </div>
                                                <div class="col-12 col-lg-3 d-flex mt-0 mt-lg-0">
                                                    <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                        <div class="col-12">
                                                            <p class="text-14 sub-text-color g-0 p-0 m-0">Select branch cover here.</p>
                                                            <p class="text-14 sub-topic-color g-0 p-0 m-0">Important guidelines:</p>
                                                            <p class="text-14 sub-text-color g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                        </div>
                                                        <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                            <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','branch-cover');">
                                                                Select Image
                                                                <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                            </button>
                                                            <!-- Hidden file input -->
                                                            <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                    <button class="col-12 col-lg-8 custom-btn m-0" onclick="addBranch(this)">Save branch</button>
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

            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 10)) {
            ?>
                <!-- faculty Edit Modal => Start -->
                <div class="modal fade br-0" id="editBranch" aria-hidden="true" aria-labelledby="editBranchLabel" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 darkColor" id="editBranchLabel">Edit Faculty</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text required">Branch Name</label>
                                            <input type="text" class="form-control custom-input mt-2" value="Portal Payment" id="edit-branch-name">
                                            <span class="text-12 text-dark-red" id="ef-name-feedback"></span>
                                        </div>
                                        <div class="col-12 d-flex flex-column">
                                            <label for="" class="text-14 main-text">Branch Cover</label>
                                            <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                    <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit-branch-cover" id="edit-branch-cover">
                                                </div>
                                                <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                                    <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                        <div class="col-12">
                                                            <p class="text-14 sub-text-color g-0 p-0 m-0">Select branch cover here.</p>
                                                            <p class="text-14 sub-topic-color g-0 p-0 m-0">Important guidelines:</p>
                                                            <p class="text-14 sub-text-color g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                        </div>
                                                        <div class="col-12 d-flex justify-content-end mt-3">
                                                            <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-branch-cover');">
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

            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('branches', 2) || UserPermissions::hasPermission('branches', 10)) {
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


            <div class="modal fade" id="removeBranchConfirmModal" tabindex="-1"
                aria-labelledby="removeBranchConfirmModalTitle" aria-hidden="true">
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
                            <h5 class="fw-semibold mb-2" id="removeBranchConfirmModalTitle">
                                Confirm Branch Removal
                            </h5>

                            <!-- Main message -->
                            <p class="text-muted mb-3">
                                You are about to remove this <strong>Branch</strong>.
                            </p>

                            <!-- Detailed impact explanation -->
                            <div class="alert alert-warning text-start small mb-4">
                                <div class="d-flex align-items-start gap-2">
                                    <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                    <div>
                                        <strong>This action will:</strong>
                                        <ul class="mb-0 ps-3">
                                            <li>Permanently remove the selected Branch</li>
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
                                    id="confirmRemoveBranch"
                                    class="btn btn-danger px-4 br-0">
                                    <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                    Remove Branch
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
?>