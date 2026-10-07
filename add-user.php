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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 2))) {
            header("Location: ./users");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Add User - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            </head>

            <body class="overflow-hidden" onload="initializeAddUser();">
                <ul class="notifications"></ul>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Users");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Users");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0 align-items-center">
                                                <div class="col-12 col-md-8 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="add-user">Add User</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="users" href="./users">Users</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="add-user">Add User</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 mt-3 mt-md-0">
                                                    <div class="row justify-content-end p-0 m-0">
                                                        <button class="custom-btn d-flex align-items-center justify-content-center w-100" data-i18n="save-user" onclick="addUser(this)">
                                                            Save User
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 m-0 g-0">
                                                    <div class="row justify-content-center p-0 m-0 g-0">
                                                        <div class="col-8 col-md-3 col-lg-4 col-xl-3 col-xxl-2 d-flex flex-column p-0 m-0">
                                                            <div class="bl-solid p-3 m-0">
                                                                <div class="row m-0 p-0 position-relative justify-content-center align-content-center" id="user-profile-image-container">
                                                                    <img src="./assets/imgs/common/empty-img.png" alt="user profile image" id="user-profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                                    <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','user-profile-image');">
                                                                        <iconify-icon icon="ic:baseline-upload" width="20" height="20" class="text-white px-2"></iconify-icon>
                                                                        <span>Upload Image</span>
                                                                    </div>
                                                                </div>
                                                                <!-- Hiddedn file input -->
                                                                <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                                <p class="text-center text-14 sub-text mt-2 mb-0" id="user-profile-image-text">Image size must be 2MB or less, with a 1:1 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>
                                                            </div>
                                                            <span class="image-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-9 col-lg-8 col-xl-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 d-block">
                                                            <div class="row m-0 p-0">
                                                                <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                    <div class="row gx-4 gy-4">
                                                                        <span class="sub-topic">Basic Profile Details</span>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Title</label>
                                                                            <select id="user-title" class="form-select custom-select mt-2">
                                                                                <option value="0">- Select -</option>
                                                                                <?php
                                                                                $user_titles = Database::search("SELECT * FROM `title`");
                                                                                while ($data = $user_titles->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $data["title_id"]; ?>"><?php echo $data["title"]; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Gender</label>
                                                                            <select id="user-gender" class="form-select custom-select mt-2">
                                                                                <option value="0">- Select -</option>
                                                                                <?php
                                                                                $user_genders = Database::search("SELECT * FROM `gender`");
                                                                                while ($data = $user_genders->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                            <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">User Role</label>
                                                                            <select id="user-role" class="form-select custom-select mt-2" onchange="enableBranchSelector(this.value)">
                                                                                <option value="0">- Select -</option>
                                                                                <?php
                                                                                $query = "SELECT * FROM `user_roles`";
                                                                                if (SessionUser::ifUserIsPro($sessionUserRole)) {
                                                                                    if ($sessionUserRole == "Super Admin") {
                                                                                        $query .= " WHERE `user_role_id` != 1";
                                                                                    }
                                                                                } else {
                                                                                    $query .= " WHERE `user_role_id` NOT IN (1,2)";
                                                                                }
                                                                                $user_roles = Database::search($query);
                                                                                while ($data = $user_roles->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $data["user_role_id"]; ?>"><?php echo $data["user_role"]; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                            <span id="role-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">First Name</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="first-name" maxlength="45">
                                                                            <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Last Name</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="last-name" maxlength="45">
                                                                            <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                        </div>

                                                                        <div class="col-12 col-md-8 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Email</label>
                                                                            <input type="email" class="form-control custom-input mt-2" id="user-email" maxlength="100">
                                                                            <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Contact Number</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="user-contact-number">
                                                                            <span id="contact-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-xxl-4 d-flex flex-column">
                                                                            <label class="text-14 main-text d-inline-flex align-items-center not-required" for="">
                                                                                NIC
                                                                                <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="National Id Card Number">
                                                                                    <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                                </span>
                                                                            </label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="user-national-id" onkeypress="checkValidChar(event)">
                                                                            <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-xxl-4 d-flex flex-column">
                                                                            <label class="text-14 main-text not-required" for="">Date of Birth</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="user-dob">
                                                                            <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 d-flex flex-column d-none" id="branch-selector-area">
                                                                            <label class="text-14 main-text required" for="">Select Branch</label>
                                                                            <select name="" id="branch-selector" class="form-select custom-select mt-2">
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
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row gx-4 gy-3">
                                                        <span class="sub-topic">Advanced Profile Details</span>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Address Line 1</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="address-line-1" maxlength="100">
                                                            <span id="address-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Address Line 2</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="address-line-2" maxlength="100">
                                                            <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Province</label>
                                                            <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict();">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $province = Database::search("SELECT * FROM `province`");
                                                                while ($data = $province->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="province-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">District</label>
                                                            <select name="" id="district" disabled class="form-select custom-select mt-2" onchange="loadCities();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="district-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">City</label>
                                                            <select name="" id="city" disabled class="form-select custom-select mt-2">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span id="city-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Postal Code</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="postal-code" maxlength="5">
                                                            <span id="pcode-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-lg-8 d-flex flex-column">

                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row p-0 m-0 gx-4 gy-3">
                                                        <span class="sub-topic p-0 m-0">Optional Details</span>
                                                        <div class="col-12 d-flex flex-column p-0 ">
                                                            <label class="text-14 main-text" for="">Upload CV</label>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <div class="d-flex flex-row gap-4 flex-nowrap">
                                                                    <button class="custom-btn px-5" id="file-selector">Browse Files</button>
                                                                </div>

                                                                <input type="file" class="d-none" id="file-selector-input" accept=".pdf">
                                                                <div class="col-6 px-1 mt-4">
                                                                    <div class="row" id="list-section">

                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span id="cv-feedback" class="text-dark-red text-12"></span>

                                                        </div>
                                                        <div class="col-12 d-flex flex-column p-0 ">
                                                            <label class="text-14 main-text" for="">About</label>
                                                            <div id="div_editor1" class="br-0 mt-2"></div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column p-0">
                                                            <label class="text-14 main-text" for="">BIO Informations</label>
                                                            <div id="div_editor2" class="br-0 mt-2"></div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column p-0">
                                                            <label class="text-14 main-text" for="">Achivements</label>
                                                            <div id="div_editor3" class="br-0 mt-2"></div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column p-0">
                                                            <label class="text-14 main-text" for="">Experience</label>
                                                            <div id="div_editor4" class="br-0 mt-2 mb-4"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3">
                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                            <button class="custom-btn w-100" data-i18n="save-user" onclick="addUser(this);">Save User</button>
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

                <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Crop Image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="image" class="img-fluid" />
                            </div>
                            <div class="modal-footer">
                                <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>
            </body>

            </html>
<?php
        }
    }
}
?>