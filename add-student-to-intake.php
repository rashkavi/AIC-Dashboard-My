<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/Limitation.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes`
                                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
                                        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
                                        WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intake-details");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id))) {
                    header("Location: ./intake-details.php?intake-id=" . $id);
                } else {

                    $countries = array();
                    $countries_rs = Database::search("SELECT * FROM `countries`");
                    while ($data = $countries_rs->fetch_assoc()) {
                        array_push($countries, [
                            'id' => $data['country_id'],
                            'name' => $data['country_name']
                        ]);
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Student to Intake - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeAddStudent(),initializeStudentDocumentUploads();">
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
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>

                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Student</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?php echo $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (Limitation::availableStudents()) {
                                                        ?>
                                                            <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                                <div class="row justify-content-end gx-3">
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn w-100" onclick="addStudentToIntake(this,<?= $id ?>);">Add Student</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php
                                                if (!Limitation::availableStudents()) {
                                                    echo Limitation::getLimitExceededMessage();
                                                } else {
                                                ?>
                                                    <div class="col-12 p-4 component-theme">
                                                        <div class="row p-0 m-0 align-content-center">
                                                            <div class="col-12 p-0 m-0 g-0">
                                                                <div class="row justify-content-center p-0 m-0 g-0">
                                                                    <div class="col-8 col-md-3 col-xl-3 col-xxl-2 d-flex flex-column p-0 m-0">
                                                                        <div class="bl-solid p-3 m-0">
                                                                            <div class="row m-0 p-0 position-relative justify-content-center align-content-center" id="profile-image-container">
                                                                                <img src="./assets/imgs/common/empty-img.png" alt="user profile image" id="profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                                                <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','profile-image');">
                                                                                    <iconify-icon icon="ic:baseline-upload" width="20" height="20" class="text-white px-2"></iconify-icon>
                                                                                    <span>Upload Image</span>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Hiddedn file input -->
                                                                            <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                                            <p class="text-center text-14 sub-text mt-2 mb-0" id="profile-image-text">Image size must be 2MB or less, with a 1:1 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>
                                                                        </div>
                                                                        <span id="image-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-9 col-xl-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 pe-lg-0 d-block">
                                                                        <div class="row m-0 p-0">
                                                                            <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                                <div class="row gx-4 gy-4">
                                                                                    <span class="sub-topic">Personal Details</span>
                                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Student Id</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="student-intake-id">
                                                                                        <span id="student-intake-id-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Select Title</label>
                                                                                        <select class="form-select custom-select mt-2" aria-label="Default select example" id="title-selector">
                                                                                            <option selected value="0">- Select -</option>
                                                                                            <?php
                                                                                            $titleResult = Database::search("SELECT * FROM `title`");
                                                                                            $titles = [];
                                                                                            while ($data = $titleResult->fetch_assoc()) {
                                                                                                $titles[] = $data;
                                                                                            }
                                                                                            foreach ($titles as $title) {
                                                                                            ?>
                                                                                                <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="title-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">First Name</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="first-name" maxlength="45">
                                                                                        <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Last Name</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="last-name" maxlength="45">
                                                                                        <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-7 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Full Name (Certificate Name)</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="full-name" maxlength="150">
                                                                                        <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-5 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Name With Initials</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="name-with-initials" maxlength="45">
                                                                                        <span id="nwi-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Select Gender</label>
                                                                                        <select class="form-select custom-select mt-2" aria-label="Default select example" id="gender-selector">
                                                                                            <option selected value="0">- Select -</option>
                                                                                            <?php
                                                                                            $genders = Database::search("SELECT * FROM `gender`");
                                                                                            while ($data = $genders->fetch_assoc()) {
                                                                                            ?>
                                                                                                <option value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Select Marital Status<Title></Title></label>
                                                                                        <select class="form-select custom-select mt-2" aria-label="Default select example" id="marital-status-selector">
                                                                                            <option selected value="0">- Select -</option>
                                                                                            <?php
                                                                                            $marital_status = Database::search("SELECT * FROM `marital_status`");
                                                                                            while ($data = $marital_status->fetch_assoc()) {
                                                                                            ?>
                                                                                                <option value="<?php echo $data["marital_status_id"]; ?>"><?php echo $data["marital_status"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="marital-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column justify-content-end">
                                                                                        <label class="text-14 main-text d-inline-flex align-items-center not-required" for="">
                                                                                            NIC
                                                                                            <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="National Id Card Number">
                                                                                                <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                                            </span>
                                                                                        </label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="nic" maxlength="12">
                                                                                        <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Passport Number</label>
                                                                                        <input type="text" class="form-control custom-input mt-2" id="passport">
                                                                                        <span id="passport-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Date of Birth</label>
                                                                                        <input type="date" class="form-control custom-input mt-2" id="dob">
                                                                                        <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Nationality</label>
                                                                                        <select class="form-select custom-select mt-2" id="nationality">
                                                                                            <?php
                                                                                            foreach ($countries as $country) {
                                                                                            ?>
                                                                                                <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="nationality-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Country of Birth</label>
                                                                                        <select class="form-select custom-select mt-2" id="country-of-birth">
                                                                                            <?php
                                                                                            foreach ($countries as $country) {
                                                                                            ?>
                                                                                                <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="cob-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Country of Residence</label>
                                                                                        <select class="form-select custom-select mt-2" id="country-of-residence" onchange="changeCountryOfResidence(this.value)">
                                                                                            <?php
                                                                                            foreach ($countries as $country) {
                                                                                            ?>
                                                                                                <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span id="cor-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <div class="row gx-4 gy-3">
                                                                    <span class="sub-topic">Contact Details</span>

                                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Email</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="email" maxlength="100">
                                                                        <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Mobile</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="mobile" maxlength="10" onkeypress="checkDigit(event)">
                                                                        <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                        <label class="text-14 main-text required" for="">Whatsapp</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="whatsapp" maxlength="10" onkeypress="checkDigit(event)">
                                                                        <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Address Line 1</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="address" maxlength="100">
                                                                        <span id="address-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Address Line 2</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="address2" maxlength="100">
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
                                                                        <select name="" id="district" class="form-select custom-select mt-2" onchange="loadCities();">
                                                                            <option value="0">- Select -</option>
                                                                            <?php
                                                                            $district = Database::search("SELECT * FROM `district`");
                                                                            while ($data = $district->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["district_id"]; ?>"><?php echo $data["district"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="district-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">City</label>
                                                                        <select name="" id="city" class="form-select custom-select mt-2">
                                                                            <option value="0">- Select -</option>
                                                                            <?php
                                                                            $city = Database::search("SELECT * FROM `city`");
                                                                            while ($data = $city->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["city_id"]; ?>"><?php echo $data["city"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="city-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Postal Code</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="postal-code" maxlength="5">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <div class="row gx-4 gy-3">
                                                                    <span class="sub-topic">Academic Details</span>

                                                                    <div class="col-12 d-flex flex-column">
                                                                        <label class="text-14 main-text">Name Should be in the Certificate</label>
                                                                        <input type="text"
                                                                            class="form-control custom-input mt-2"

                                                                            id="certificate-name"
                                                                            maxlength="100">
                                                                        <span id="certificate-name-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 d-flex flex-column">
                                                                        <label class="text-14 main-text">School Attended</label>
                                                                        <input type="text"
                                                                            class="form-control custom-input mt-2"

                                                                            id="school">
                                                                        <span id="school-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                        <label class="text-14 main-text">O/L Results</label>
                                                                        <input type="text"
                                                                            class="form-control custom-input mt-2"

                                                                            id="ol-results">
                                                                        <span id="ol-results-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                        <label class="text-14 main-text">A/L Results</label>
                                                                        <input type="text"
                                                                            class="form-control custom-input mt-2"

                                                                            id="al-results">
                                                                        <span id="al-results-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 d-flex flex-column">
                                                                        <label class="text-14 main-text">Work Experience</label>
                                                                        <textarea class="form-control custom-input mt-2"
                                                                            rows="5"
                                                                            id="work-experience"></textarea>
                                                                        <span id="work-experience-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                                        <label class="text-14 main-text">MS Team Username</label>
                                                                        <input type="text"
                                                                            class="form-control custom-input mt-2"

                                                                            id="team-username">
                                                                        <span id="team-username-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <div class="row gx-4 gy-3">
                                                                    <span class="sub-topic">Gurdian Details</span>
                                                                    <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Guardian Relationship</label>
                                                                        <select name="" id="grelationship-selector" class="form-select custom-select mt-2">
                                                                            <option value="0">- Select -</option>
                                                                            <?php
                                                                            $guardianRelationships = Database::search("SELECT * FROM `guardian_relation`");
                                                                            while ($data = $guardianRelationships->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["guardian_relation_id"]; ?>"><?php echo $data["guardian_relation"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="grelationship-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Title</label>
                                                                        <select name="" id="gtitle-selector" class="form-select custom-select mt-2">
                                                                            <option value="0">- Select -</option>
                                                                            <?php
                                                                            foreach ($titles as $title) {
                                                                            ?>
                                                                                <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                            <?php

                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="gtitle-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">First Name</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="guardian-fname" maxlength="45">
                                                                        <span id="gfname-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Last Name</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="guardian-lname" maxlength="45">
                                                                        <span id="glname-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-6 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Email</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="guardian-email" maxlength="100">
                                                                        <span id="gemail-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Mobile</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="guardian-mobile" maxlength="10">
                                                                        <span id="gmobile-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">WhatsApp</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="guardian-whatsapp" maxlength="10">
                                                                        <span id="gwhatsapp-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                </div>
                                                            </div>

                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <span class="sub-topic">Student Required Documents</span>

                                                                <div class="row mt-2" id="student-documents-wrapper">

                                                                    <!-- AIC Application -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="aic_application">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">AIC Application</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-aic-application"
                                                                                    data-doc-key="aic_application"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-aic-application-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- University Application -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="university_application">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">University Application</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-university-application"
                                                                                    data-doc-key="university_application"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-university-application-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Signed Agreement -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="signed_agreement">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Signed Agreement with Letters</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-signed-agreement"
                                                                                    data-doc-key="signed_agreement"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-signed-agreement-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Signed Program Brief -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="signed_program_brief">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Signed Program Brief</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-signed-program-brief"
                                                                                    data-doc-key="signed_program_brief"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-signed-program-brief-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Passport / NIC -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="passport_nic">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Passport / NIC</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-passport-nic"
                                                                                    data-doc-key="passport_nic"
                                                                                    accept=".pdf,.jpg,.jpeg,.png">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-passport-nic-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Academic Documents -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="academic_documents">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Academic Documents</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop files here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse Files
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-academic-documents"
                                                                                    data-doc-key="academic_documents"
                                                                                    multiple
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-academic-documents-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Other Entry Documents -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="other_entry_documents">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Other Entry Documents</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop files here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse Files
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-other-entry-documents"
                                                                                    data-doc-key="other_entry_documents"
                                                                                    multiple
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-other-entry-documents-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Payment Slip -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="payment_slip">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Payment Slip</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-payment-slip"
                                                                                    data-doc-key="payment_slip"
                                                                                    accept=".pdf,.jpg,.jpeg,.png">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-payment-slip-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Check List Signed by Manager -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="manager_checklist">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Check List Document Signed by Manager</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-manager-checklist"
                                                                                    data-doc-key="manager_checklist"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-manager-checklist-feedback"></span>
                                                                        </div>
                                                                    </div>
                                                                    <!-- Discount Approved Document -->
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="discount_approved_document">
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title">Discount Approved Document</span>
                                                                                <!-- <span class="doc-required">Required</span> -->
                                                                            </div>

                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                <span class="doc-or-text">OR</span>

                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                    Browse File
                                                                                </button>

                                                                                <input type="file"
                                                                                    class="d-none student-doc-input"
                                                                                    id="doc-discount-approved-document"
                                                                                    data-doc-key="discount_approved_document"
                                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                            </div>

                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-discount-approved-document-feedback"></span>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>

                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <div class="row gx-4 gy-3">
                                                                    <span class="sub-topic">Other</span>

                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                        <label class="text-14 main-text mb-2 required">
                                                                            Entry Type
                                                                        </label>
                                                                        <div class="d-flex align-items-center gap-4 mt-2">
                                                                            <div class="form-check custom-entry-check">
                                                                                <input class="form-check-input"
                                                                                    type="radio"
                                                                                    name="entry_type"
                                                                                    id="academic-entry"
                                                                                    value="ACADEMIC"
                                                                                    checked>
                                                                                <label class="form-check-label text-14 main-text"
                                                                                    for="academic-entry">
                                                                                    Academic Entry
                                                                                </label>
                                                                            </div>
                                                                            <div class="form-check custom-entry-check">
                                                                                <input class="form-check-input"
                                                                                    type="radio"
                                                                                    name="entry_type"
                                                                                    id="other-entry"
                                                                                    value="OTHER">
                                                                                <label class="form-check-label text-14 main-text"
                                                                                    for="other-entry">
                                                                                    Other Entry
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                        <span id="entry-type-feedback"
                                                                            class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 d-flex flex-column">
                                                                        <label class="text-14 main-text" for="">Payment Option</label>
                                                                        <textarea type="text" class="form-control custom-input mt-2" id="payment-option" rows="5"></textarea>
                                                                        <span id="payment-option-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 p-0 g-0 mt-5">
                                                                <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                        <button class="custom-btn w-100" data-i18n="save-student" onclick="addStudent(this);">Save Student</button>
                                                                    </div>
                                                                </div>
                                                            </div>
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
                    </body>

                    </html>
<?php

                }
            }
        }
    }
}
?>