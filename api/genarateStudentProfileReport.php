<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $code = isset($decoded['code']) ? $decoded['code'] : 0;
                    $action = isset($decoded['action']) ? (int)$decoded['action'] : 0;

                    if (empty($code)) {
                        $status->code = 'Please enter student code';
                    } else {


                        $query = "
                        SELECT 
                            `students`.`student_id` AS 'sId', 
                            `students_has_intakes`.`student_id`,
                            `students_has_intakes`.`addmission_date`,
                            `login_details`.`first_name`, 
                            `login_details`.`last_name`, 
                            `login_details`.`email`, 
                            `students`.`mobile`, 
                            `login_details`.`whatsapp`, 
                            `name_with_initials`,
                            `full_name`,
                            `profile_img`, 
                            `date_registered`, 
                            `title`.`title`, 
                            `status`.`status`,
                            `status`.`status_id`,
                           `gender`.`gender`,
                           `date_of_birth`,
                           `nic`,
                           `passport`,
                           `intake`,
                           `programme`,
                           `faculty`,
                           `branch`,
                           `address_line_1`,
                           `address_line_2`,
                           `guardian_title`.`title` AS `guardian_title`,
                           `guardian_relation`.`guardian_relation`,
                           `guardian_details`.`first_name` AS `guardian_first_name`,
                           `guardian_details`.`last_name` AS `guardian_last_name`,
                           `guardian_details`.`email` AS `guardian_email`,
                           `guardian_details`.`mobile` AS `guardian_mobile`,
                           `guardian_details`.`whatsapp` AS `guardian_whatsapp`
                        FROM 
                            `students`
                        INNER JOIN 
                            `login_details` 
                            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN 
                            `title` 
                            ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `gender` ON `students`.`gender_gender_id` = `gender`.`gender_id`
                        INNER JOIN 
                            `status` 
                            ON `login_details`.`status_status_id` = `status`.`status_id`
                         INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                         INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                         INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                         INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                         INNER JOIN `branches` ON `faculties`.`branches_branch_id` = `branches`.`branch_id`
                        LEFT JOIN `student_has_address` ON `students`.`student_id` = `student_has_address`.`students_student_id`
                        LEFT JOIN `guardian_details` ON `students`.`student_id` = `guardian_details`.`students_student_id`
                        LEFT JOIN `guardian_relation` ON `guardian_details`.`guardian_relation_guardian_relation_id` = `guardian_relation`.`guardian_relation_id`
                        LEFT JOIN `title` AS `guardian_title` ON `guardian_details`.`title_title_id` = `guardian_title`.`title_id`
                        WHERE `students_has_intakes`.`student_id` = '" . $code . "'
                       
                    ";

                        $results = Database::search($query);

                        if ($results->num_rows == 0) {
                            http_response_code(404);
                            $status->error = 'No student found';
                        } else {
                            $student = $results->fetch_assoc();

                            if (!empty($student["profile_img"])) {
                                $imagePath = Project::getBasePortalUrl() . "/assets/imgs/students/" . $student["profile_img"];
                                if (file_exists('../assets/imgs/students/' . $student["profile_img"])) {
                                    $imagePath = './assets/imgs/students/' . $student["profile_img"];
                                }
                            } else {
                                $imagePath = "./assets/imgs/users/default-user.webp";
                            }
?>

                            <div class="col-12 report-root"
                                data-student-name="<?= $student["first_name"] . ' ' . $student["last_name"]; ?>"
                                data-student-code="<?= $student["student_id"]; ?>">
                                <div class="row component-theme m-0">
                                    <div class="d-flex justify-content-between align-items-center">

                                        <!-- LEFT: Logo + Name -->
                                        <div class="d-flex align-items-center">
                                            <img src="./assets/imgs/common/logo.png" alt="Logo" class="institute-logo me-3">
                                        </div>

                                        <!-- RIGHT: Contact Info -->
                                        <div class="text-end report-contact">
                                            <span class="d-block text-16 fw-semibold">TIMES ACADEMY</span>
                                            <span class="d-block text-14">58/A1, Nugegoda Road, Peliyamulla</span>
                                            <span class="d-block text-14">+94 77 888 37 36 / +94 11 22 62 62</span>
                                        </div>
                                    </div>

                                    <?php
                                    if ($action == 1) {
                                    ?>
                                        <div class="col-12 mt-5">
                                            <div class="student-report-premium">

                                                <!-- Top Section -->
                                                <div class="student-report-top">
                                                    <div class="student-report-profile">
                                                        <div class="student-report-image-wrap">
                                                            <img src="<?= $imagePath ?>" alt="Student Photo" class="student-report-image">
                                                        </div>

                                                        <div class="student-report-basic">
                                                            <h2 class="student-report-name"><?php echo $student["first_name"] . " " . $student["last_name"]; ?></h2>
                                                            <div class="student-report-meta-wrap">
                                                                <div class="student-meta-card">
                                                                    <span class="student-meta-label">Student Code</span>
                                                                    <span class="student-meta-value"><?php echo $student["student_id"]; ?></span>
                                                                </div>
                                                                <div class="student-meta-card">
                                                                    <span class="student-meta-label">Status</span>
                                                                    <span class="student-meta-value <?= strtolower($student["status"]) ?>"><?= $student["status"] ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Details Section -->
                                                <div class="student-report-section">
                                                    <div class="student-report-section-title">Personal Information</div>

                                                    <div class="student-report-grid">
                                                        <div class="student-detail-box">
                                                            <label>Title</label>
                                                            <div><?= $student["title"] ?>.</div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Name With Initials</label>
                                                            <div><?= $student['name_with_initials'] ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>First Name</label>
                                                            <div><?= $student['first_name'] ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>Last Name</label>
                                                            <div><?= $student['last_name'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box full-width">
                                                            <label>Full Name</label>
                                                            <div><?= $student['full_name'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Date of Birth</label>
                                                            <div><?php echo $student["date_of_birth"]; ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Gender</label>
                                                            <div><?php echo $student["gender"]; ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>NIC</label>
                                                            <div><?= $student['nic'] ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>Passport</label>
                                                            <div><?= $student['passport'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Mobile Number</label>
                                                            <div><?php echo $student["mobile"]; ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>WhatsApp Number</label>
                                                            <div><?php echo $student["whatsapp"]; ?></div>
                                                        </div>

                                                        <div class="student-detail-box full-width">
                                                            <label>Email Address</label>
                                                            <div><?php echo $student["email"]; ?></div>
                                                        </div>

                                                        <div class="student-detail-box full-width">
                                                            <label>Address</label>
                                                            <div><?php echo $student["address_line_1"] == NULL ? "N/A" : $student["address_line_1"] . " " . $student["address_line_2"]; ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Academic Section -->
                                                <div class="student-report-section">
                                                    <div class="student-report-section-title">Academic Information</div>

                                                    <div class="student-report-grid">
                                                        <div class="student-detail-box">
                                                            <label>Branch</label>
                                                            <div><?= $student['branch'] ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>Faculty</label>
                                                            <div><?= $student['faculty'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Programme</label>
                                                            <div><?= $student['programme'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Intake</label>
                                                            <div><?= $student['intake'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Admission Date</label>
                                                            <div><?= date_format(new DateTime($student['addmission_date']), 'Y-m-d h:i A') ?></div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <!-- Guardian Section -->
                                                <div class="student-report-section">
                                                    <div class="student-report-section-title">Guardian Information</div>

                                                    <div class="student-report-grid">
                                                        <div class="student-detail-box">
                                                            <label>Guardian Relationship</label>
                                                            <div><?= $student['guardian_relation'] ?></div>
                                                        </div>
                                                        <div class="student-detail-box">
                                                            <label>Guardian Name</label>
                                                            <div><?= $student['guardian_title'] . "." . $student['guardian_first_name'] . " " . $student['guardian_last_name'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box full-width">
                                                            <label>Guardian Email</label>
                                                            <div><?= $student['guardian_email'] == NULL ? "N/A" : $student['guardian_email'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>Mobile Number</label>
                                                            <div><?= $student['guardian_mobile'] == NULL ? "N/A" : $student['guardian_mobile'] ?></div>
                                                        </div>

                                                        <div class="student-detail-box">
                                                            <label>WhatsApp Number</label>
                                                            <div><?= $student['guardian_whatsapp'] == NULL ? "N/A" : $student['guardian_whatsapp'] ?></div>
                                                        </div>

                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    <?php
                                    } else if ($action == 2) {
                                    ?>
                                        <div class="col-12 mt-5">
                                            <div class="student-report-premium">

                                                <!-- Top Section -->
                                                <div class="student-report-top">
                                                    <div class="student-report-profile">

                                                        <div class="student-report-basic">
                                                            <h2 class="student-report-name">Attendance - <?php echo $student["first_name"] . " " . $student["last_name"]; ?> (<?= $code ?>)</h2>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="students-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                            <thead>
                                                                <tr class="br-t-5">

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Batch ID</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleAttendnacePagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium">Date Time</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Updated By</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php

                                                                $query = "
                                                            SELECT 
                                                                `students_has_intakes`.`student_id` AS 'sId', 
                                                                `first_name`, 
                                                                `last_name`, 
                                                                `email`, 
                                                                `mobile`, 
                                                                `whatsapp`, 
                                                                `students`.`profile_img`, 
                                                                `date_updated`, 
                                                                `title`.`title`, 
                                                                `student_in_out_status`.`student_in_out_status`,
                                                                `student_in_out_status_id`,
                                                            `users`.`user_fname`,
                                                            `users`.`user_lname`,
                                                            `users`.`profile_img` AS 'user_profile_img',
                                                            `title2`.`title` AS 'user_title'
                                                            FROM 
                                                                `student_in_out_records`
                                                            INNER JOIN `students_has_intakes` 
                                                                ON `student_in_out_records`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                                            INNER JOIN 
                                                                `students` 
                                                                ON `students_has_intakes`.`students_student_id` = `students`.`student_id`

                                                            INNER JOIN 
                                                                `login_details` 
                                                                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                            INNER JOIN 
                                                                `title` 
                                                                ON `students`.`title_title_id` = `title`.`title_id`

                                                            INNER JOIN 
                                                                `student_in_out_status` 
                                                                ON `student_in_out_records`.`student_in_out_status_student_in_out_status_id` = `student_in_out_status`.`student_in_out_status_id`
                                                            INNER JOIN
                                                                    `users`
                                                                    ON `student_in_out_records`.`users_user_id` = `users`.`user_id`
                                                            INNER JOIN 
                                                                `title` as `title2` 
                                                                ON `users`.`title_title_id` = `title2`.`title_id`
                                                           WHERE `students_has_intakes`.`student_id` = '" . $code . "'
                                                            ";

                                                                $results = Database::search($query);

                                                                if ($results->num_rows > 0) {
                                                                    while ($data = $results->fetch_assoc()) {
                                                                ?>
                                                                        <tr>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $student["intake"]; ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "1"     => "table-btn-success",
                                                                                    "2"  => "table-btn-danger",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["student_in_out_status_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["student_in_out_status"]; ?></button>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_updated"])); ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="13" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    } else if ($action == 3) {
                                    ?>
                                        <div class="col-12 mt-5">
                                            <div class="student-report-premium">

                                                <!-- Top Section -->
                                                <div class="student-report-top">
                                                    <div class="student-report-profile">

                                                        <div class="student-report-basic">
                                                            <h2 class="student-report-name">Incidents - <?php echo $student["first_name"] . " " . $student["last_name"]; ?> (<?= $code ?>)</h2>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="students-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                            <thead>
                                                                <tr class="br-t-5">

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Batch ID</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Incident Type</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Description</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Location</th>
                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleAttendnacePagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium">Date Time</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>

                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php

                                                                $query = "
                                                            SELECT 
                                                               
                                                                `incident_type`.`incident_type`,
                                                                `incident_type`.`incident_type_id`,
                                                                `students_has_incidents`.`date_added`,
                                                                `students_has_incidents`.`students_has_incidents_id`,
                                                                `students_has_incidents`.`incident_date` AS 'date',
                                                                `students_has_incidents`.`incident_time` AS 'time',
                                                                `students_has_incidents`.`location`,
                                                               `note`
                                                            FROM `students_has_incidents`
                                                            INNER JOIN `students_has_intakes` 
                                                                ON `students_has_incidents`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                                            INNER JOIN `incident_type` 
                                                                ON `students_has_incidents`.`incident_type_incident_type_id` = `incident_type`.`incident_type_id`
                                                          
                                                            WHERE `students_has_intakes`.`student_id` = '" . $student["student_id"] . "'
                                                            ";

                                                                $results = Database::search($query);

                                                                if ($results->num_rows > 0) {
                                                                    while ($data = $results->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $student["intake"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["incident_type"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["note"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["location"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date"] . " " . $data["time"])); ?></span></td>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="13" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    } else if ($action == 4) {
                                    ?>
                                        <div class="col-12 mt-5">
                                            <div class="student-report-premium">

                                                <!-- Top Section -->
                                                <div class="student-report-top">
                                                    <div class="student-report-profile">

                                                        <div class="student-report-basic">
                                                            <h2 class="student-report-name">Payments - <?php echo $student["first_name"] . " " . $student["last_name"]; ?> (<?= $code ?>)</h2>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="payments-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                            <thead>
                                                                <tr class="br-t-5">

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Type</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Discount</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty Amount</th>

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                            <span class="text-14 main-text fw-medium me-2">Payable Amount</span>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleSelectedPaymentCategoryPagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                            <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment method</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 15)) {
                                                                    ?>
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php

                                                                $query = "
                                                                        SELECT 
                                                                            `cp`.`course_payments_id`,
                                                                             `cp`.`discount`,
                                                                            `cp`.`discount_type_discount_type_id`,
                                                                            `ps`.`payment_status`,
                                                                            `ps`.`payment_status_id`,
                                                                            `pm`.`payment_method`,
                                                                              `pm`.`payment_method_id`,
                                                                            `cp`.`amount`,
                                                                            `cp`.`due_date`,
                                                                            `cpp`.`paid_amount`,
                                                                            `shi`.`student_id`,
                                                                            `ld`.`first_name`,
                                                                            `ld`.`last_name`,
                                                                            `t`.`title`,
                                                                            `s`.`profile_img`,
                                                                            MIN(`pl`.`date_updated`) AS `date_added`,
                                                                            SUM(`pp`.`amount`) AS `panelty_charge`,
                                                                            SUM(`cpp`.`panelty_amount`) AS `total_sub_penalty_amount`,
                                                                            `cpp`.`discount_amount` AS `sub_discount`,
                                                                            `cpp`.`course_payment_has_payment_id`,
                                                                            `cpp`.`date_paid`,
                                                                            `c`.`code`,
                                                                            `c`.`decimal_places`,
                                                                            `pgp`.`payment_type`
                                                                        
                                                                        FROM `course_payment_has_payments` `cpp`
                                                                        INNER JOIN `course_payments` `cp`
                                                                            ON `cp`.`course_payments_id` = `cpp`.`course_payments_course_payments_id`
                                                                        INNER JOIN `payment_status` `ps` 
                                                                            ON `cpp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                        INNER JOIN `payment_type` `pgp` 
                                                                            ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                                                                        INNER JOIN `payment_method` `pm` 
                                                                            ON `cpp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
                                                                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                        INNER JOIN `students_has_intakes` AS `shi` 
                                                                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                        INNER JOIN `students` AS `s` 
                                                                            ON `shi`.`students_student_id` = `s`.`student_id`
                                                                        INNER JOIN `login_details` AS `ld`
                                                                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                        INNER JOIN `title` AS `t` 
                                                                            ON `s`.`title_title_id` = `t`.`title_id`
                                                                        INNER JOIN `intakes` AS `i` 
                                                                            ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                                        INNER JOIN `programmes` AS `p` 
                                                                            ON `i`.`programmes_programme_id` = `p`.`programme_id`
                                                                        INNER JOIN `faculties` AS `f` 
                                                                            ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
                                                                         INNER JOIN `payment_category` AS `pc` 
                                                                            ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                        INNER JOIN `currencies` AS `c`
                                                                            ON `pc`.`currencies_currency_id` = `c`.`currency_id`  
                                                                        LEFT JOIN `payment_logs` AS `pl`
                                                                            ON `cp`.`course_payments_id` = `pl`.`course_payments_course_payments_id`
                                                                            AND `cpp`.`course_payment_has_payment_id` = `pl`.`course_payment_has_payment_id`
                                                                        LEFT JOIN `panelty_payments` AS `pp`
                                                                            ON `cp`.`course_payments_id` = `pp`.`course_payments_course_payments_id`      
                                                                        WHERE `s`.`student_id`='" . $student['sId'] . "'
                                                                        AND `shi`.`student_id` = '" . $code . "'
                                                                        GROUP BY `cp`.`course_payments_id`
                                                                        ORDER BY `cp`.`course_payments_id` DESC
                                                                        ";

                                                                $results = Database::search($query);

                                                                if ($results->num_rows > 0) {
                                                                    while ($data = $results->fetch_assoc()) {
                                                                        $discount = $data['discount'];
                                                                        if ($data['discount_type_discount_type_id'] == 3) {
                                                                            $discount = $data['amount'] * $data['discount'] / 100;
                                                                        }
                                                                        $totalDiscount = $data['sub_discount'] + $discount;
                                                                        $totalPenalty = $data['panelty_charge'] + $data['total_sub_penalty_amount'];
                                                                        $payableAmount = $data['amount'] - $totalDiscount + $totalPenalty;
                                                                        $balance = $payableAmount - $data['paid_amount'];
                                                                ?>
                                                                        <tr>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_type"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data['amount'], $data["decimal_places"]) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($totalDiscount, $data["decimal_places"]) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($totalPenalty, $data["decimal_places"]) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($payableAmount, $data["decimal_places"]) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data['paid_amount'], $data["decimal_places"]) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"] == '' ? "N/A" : date("Y-m-d", strtotime($data["date_paid"])); ?>
                                                                                </span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "1"     => "table-btn-orange",
                                                                                    "2"  => "table-btn-pink",
                                                                                    "3"  => "table-btn-purple",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["payment_method_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_method"]; ?></button>
                                                                            </td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "0" => "table-btn-purple",
                                                                                    "1" => "table-btn-yellow",
                                                                                    "2" => "table-btn-orange",
                                                                                    "3" => "table-btn-success",
                                                                                    "4" => "table-btn-danger",
                                                                                    "5" => "table-btn-orange",
                                                                                    "6" => "table-btn-success",
                                                                                    "7" => "table-btn-danger",
                                                                                    "8" => "table-btn-success",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                            </td>
                                                                            <?php
                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 15)) {
                                                                            ?>
                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="payment-category-payment?payment-id=<?php echo $data['course_payment_has_payment_id']; ?>">
                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View</a></li>
                                                                                        </ul>
                                                                                    </div>
                                                                                </td>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="13" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                    ?>


                                </div>
                            </div>
<?php
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
