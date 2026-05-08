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


        $user = Database::search("SELECT * FROM `users` 
                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id` WHERE `users`.`user_id`='" . $sessionUserObject["user_id"] . "'");

        $udata = $user->fetch_assoc();

        $uAddress = Database::search("SELECT * FROM `user_has_address` 
                INNER JOIN `city` ON `user_has_address`.`city_city_id`=`city`.`city_id`
                INNER JOIN `district` ON `city`.`district_district_id`=`district`.`district_id`
                INNER JOIN `province` ON `district`.`province_province_id`=`province`.`province_id`
                WHERE `users_user_id`='" . $udata['user_id'] . "'");
        $uAddressData = $uAddress->fetch_assoc();

        $uOptional = Database::search("SELECT * FROM `optional_details` WHERE `users_user_id`='" . $udata['user_id'] . "'");
        $uOptionalData = $uOptional->fetch_assoc();

        $isHasAddressData = $uAddressData == null ? false : true;
        $isHasOptionalData = $uOptionalData == null ? false : true;
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>My Profile - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

        <body class="overflow-hidden" onload="initializeViewUser();">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-add-edit.php"; ?>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 col-md-10">
                                                <div class="row">
                                                    <span class="main-topic lh-lg">My Profile</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.history.back();">Users</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= ucwords(strtolower($udata['title'] . " " . $udata['user_fname'] . " " . $udata['user_lname'])); ?></a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                <button class="custom-btn w-100" type="button" onclick="editUser(this,'<?= $sessionUserObject['user_id'] ?>');">
                                                    Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 align-content-center">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <div class="row justify-content-center p-0 m-0 g-0">
                                                    <div class="col-10 col-md-3 col-lg-4 col-xl-3 col-xxl-2 d-flex flex-column p-0 m-0">
                                                        <div class="bl-solid p-3 m-0">
                                                            <div class="row m-0 p-0 position-relative justify-content-center align-content-center" id="user-profile-image-container">
                                                                <?php
                                                                if (!empty($udata["profile_img"]) && file_exists('./assets/imgs/users/' . $udata["profile_img"])) {
                                                                ?>
                                                                    <img src="<?= './assets/imgs/users/' . $udata["profile_img"]; ?>" alt="user profile image" id="user-profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <img src="./assets/imgs/common/empty-img.png" alt="user profile image" id="user-profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                                <?php
                                                                }
                                                                ?>
                                                                <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','user-profile-image','crop-file-input');">
                                                                    <iconify-icon icon="ic:baseline-upload" width="20" height="20" class="text-white px-2"></iconify-icon>
                                                                    <span>Upload Image</span>
                                                                </div>
                                                            </div>
                                                            <!-- Hiddedn file input -->
                                                            <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                            <p class="text-center text-14 sub-text mt-2 mb-0" id="user-profile-image-text">Image size should be under 2MB and image ration needs to be 1:1</p>
                                                        </div>
                                                        <span id="image-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-9 col-lg-8 col-xl-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 d-block">
                                                        <div class="row m-0 p-0">
                                                            <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                <div class="row gx-4 gy-4">
                                                                    <span class="sub-topic">Basic Profile Details</span>
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">User Title</label>
                                                                        <select id="user-title" class="form-select custom-select mt-2">
                                                                            <?php
                                                                            $user_titles = Database::search("SELECT * FROM `title`");
                                                                            while ($data = $user_titles->fetch_assoc()) {
                                                                                if ($data["title_id"] == $udata["title_title_id"]) {
                                                                            ?>
                                                                                    <option selected value="<?= $data["title_id"]; ?>"><?= $data["title"]; ?></option>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <option value="<?= $data["title_id"]; ?>"><?= $data["title"]; ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="utitle-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Gender</label>
                                                                        <select id="user-gender" class="form-select custom-select mt-2">
                                                                            <option value="0">- Select Gender -</option>
                                                                            <?php
                                                                            $user_genders = Database::search("SELECT * FROM `gender`");
                                                                            while ($data = $user_genders->fetch_assoc()) {

                                                                                if ($data["gender_id"] == $udata["gender_gender_id"]) {
                                                                            ?>
                                                                                    <option selected value="<?= $data["gender_id"]; ?>"><?= $data["gender"]; ?></option>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <option value="<?= $data["gender_id"]; ?>"><?= $data["gender"]; ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">User Role</label>
                                                                        <select id="user-role" class="form-control custom-select mt-2" <?= SessionUser::ifUserIsPro($sessionUserObject) ? "" : "disabled" ?>>
                                                                            <?php
                                                                            $user_roles = Database::search("SELECT * FROM `user_roles`");
                                                                            while ($data = $user_roles->fetch_assoc()) {
                                                                                if ($data["user_role_id"] == $udata["user_roles_user_role_id"]) {
                                                                            ?>
                                                                                    <option selected value="<?= $data["user_role_id"]; ?>"><?= $data["user_role"]; ?></option>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <option value="<?= $data["user_role_id"]; ?>"><?= $data["user_role"]; ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="urole-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">First Name</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="first-name" value="<?= ucwords(strtolower($udata["user_fname"])); ?>">
                                                                        <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Last Name</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="last-name" value="<?= ucwords(strtolower($udata["user_lname"])); ?>">
                                                                        <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-6 col-xxl-6 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Email</label>
                                                                        <input type="email" class="form-control custom-input mt-2" id="user-email" value="<?= $udata["user_email"]; ?>">
                                                                        <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-xxl-4 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Contact Number</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="user-contact-number" onkeypress="checkDigit(event)" value="<?= $udata["user_mobile"]; ?>">
                                                                        <span id="contact-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-xxl-4 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">National Id Number</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="user-national-id" onkeypress="checkValidChar(event)" value="<?= $udata["nic"]; ?>">
                                                                        <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-xxl-4 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Date of Birth</label>
                                                                        <input type="date" class="form-control custom-input mt-2" id="user-dob" value="<?= $udata["dob"]; ?>">
                                                                        <span id="dob-feedback" class="text-dark-red text-12"></span>
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
                                                        <input type="text" class="form-control custom-input mt-2" id="address-line-1" value="<?= $isHasAddressData ? $uAddressData["address_line_1"] : ""; ?>">
                                                        <span id="address-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">Address Line 2</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="address-line-2" value="<?= $isHasAddressData != null ? $uAddressData["address_line_2"] : ""; ?>">
                                                        <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">Province</label>
                                                        <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict();">
                                                            <option value="0">Select Province</option>
                                                            <?php
                                                            $province = Database::search("SELECT * FROM `province`");
                                                            while ($data = $province->fetch_assoc()) {
                                                                if ($isHasAddressData && $data["province_id"] == $uAddressData["province_id"]) {
                                                            ?>
                                                                    <option selected value="<?= $data["province_id"]; ?>"><?= $data["province"]; ?></option>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <option value="<?= $data["province_id"]; ?>"><?= $data["province"]; ?></option>
                                                            <?php
                                                                }
                                                            }
                                                            ?>

                                                        </select>
                                                        <span id="province-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">District</label>
                                                        <select name="" id="district" class="form-select custom-select mt-2" onchange="loadCities();">
                                                            <option value="0">Select District</option>
                                                            <?php
                                                            $district = Database::search("SELECT * FROM `district`");
                                                            while ($data = $district->fetch_assoc()) {
                                                                if ($isHasAddressData && $data["district_id"] == $uAddressData["district_id"]) {
                                                            ?>
                                                                    <option selected value="<?= $data["district_id"]; ?>"><?= $data["district"]; ?></option>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <option value="<?= $data["district_id"]; ?>"><?= $data["district"]; ?></option>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                        <span id="district-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">City</label>
                                                        <select name="" id="city" class="form-select custom-select mt-2">
                                                            <option value="0">Select City</option>
                                                            <?php
                                                            $city = Database::search("SELECT * FROM `city`");
                                                            while ($data = $city->fetch_assoc()) {
                                                                if ($isHasAddressData && $data["city_id"] == $uAddressData["city_id"]) {
                                                            ?>
                                                                    <option selected value="<?= $data["city_id"]; ?>"><?= $data["city"]; ?></option>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <option value="<?= $data["city_id"]; ?>"><?= $data["city"]; ?></option>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                        <span id="city-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text" for="">Postal Code</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="postal-code" value="<?= $isHasAddressData ? $uAddressData["postal_code"] : ""; ?>">
                                                        <span id="pcode-feedback" class="text-dark-red text-12"></span>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="col-12 p-0 g-0 mt-5">
                                                <div class="row p-0 m-0 gx-4 gy-3">
                                                    <span class="sub-topic p-0 m-0">Optional Details</span>
                                                    <div class="col-12 d-flex flex-column p-0 ">
                                                        <label class="text-14 main-text" for="">Upload CV</label>

                                                        <div class="col-12 px-1 mt-2">
                                                            <div class="row">
                                                                <?php
                                                                if ($isHasOptionalData) {
                                                                    if (!empty($uOptionalData["cv"]) && file_exists("./assets/uploads/cv/" . $uOptionalData["cv"])) {
                                                                ?>
                                                                        <div class="col-12 col-md-4 my-3 file-item cursor-pointer" onclick="viewFile('<?= $uOptionalData['cv']; ?>');">
                                                                            <div class="p-3 bl-solid">
                                                                                <div class="row">
                                                                                    <div class="col-2">
                                                                                        <img src="assets/imgs/icons/pdf.png" alt="">
                                                                                    </div>
                                                                                    <div class="col-8">
                                                                                        <div class="file-name">
                                                                                            <div class="name"><?= $assignmentData["attachment"] ?></div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                <?php
                                                                    }
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
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
                                                        <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        <input type="file" id="file-input2" class="d-none" accept=".pdf, .doc, .docx, .ppt, .pptx">
                                                    </div>
                                                    <div class="col-12 d-flex flex-column p-0 ">
                                                        <label class="text-14 main-text" for="">About Lecturer</label>
                                                        <div id="div_editor1" class="br-0 mt-2">
                                                            <?php
                                                            if ($isHasOptionalData) {
                                                                echo $uOptionalData["about"];
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column p-0">
                                                        <label class="text-14 main-text" for="">BIO Informations</label>
                                                        <div id="div_editor2" class="br-0 mt-2">
                                                            <?php
                                                            if ($isHasOptionalData) {
                                                                echo $uOptionalData["bio"];
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column p-0">
                                                        <label class="text-14 main-text" for="">Achivements</label>
                                                        <div id="div_editor3" class="br-0 mt-2">
                                                            <?php
                                                            if ($isHasOptionalData) {
                                                                echo $uOptionalData["achivements"];
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 d-flex flex-column p-0">
                                                        <label class="text-14 main-text" for="">Experience</label>
                                                        <div id="div_editor4" class="br-0 mt-2 mb-4">
                                                            <?php
                                                            if ($isHasOptionalData) {
                                                                echo $uOptionalData["experience"];
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 p-0 g-0 mt-5">
                                                <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                        <button class="custom-btn w-100" onclick="editUser(this,'<?= $sessionUserObject['user_id'] ?>');">Save Changes</button>
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
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="crop-model-close"></button>
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
?>