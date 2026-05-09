<?php
$status = new stdClass();
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $email = $decoded['email'];
            $password = $decoded['password'];
            $rememberMe = $decoded['rememberMe'];
            if (empty($email)) {
                $status->email =  "Please enter your email";
            }
            if (empty($password)) {
                $status->password =  "Please enter your password";
            }
            if (!property_exists($status, 'email') && !property_exists($status, 'password')) {
                $user = Database::search("SELECT * FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                
                WHERE `user_email`='" . $email . "'");
                if ($user->num_rows > 0) {
                    $data = $user->fetch_assoc();
                    if (password_verify($password, $data["user_password"])) {
                        if ($data["status_status_id"] == 1) {
                            include_once "../includes/UserClass.php";
                            $data["user_password"] = null;
                            $userPermissions_rs = Database::search(
                                "
                                                              
                                SELECT 'users' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='1'

                                UNION ALL
                              
                                SELECT 'attendance' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='22'

                                UNION ALL

                                SELECT 'students' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='2'

                                UNION ALL

                                SELECT 'faculties' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='3'

                                UNION ALL

                                SELECT 'payments' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='19'

                                UNION ALL

                                SELECT 'super_lecture_schedule' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='11'

                                UNION ALL

                                SELECT 'super_lecture_recording' AS permission_type, users_user_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_other
                                INNER JOIN `permission_to` ON `users_has_permission_to_other`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='13'

                                UNION ALL

                                SELECT 'faculty' AS permission_type, faculties_faculty_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_faculties
                                INNER JOIN `permission_to` ON `users_has_permission_to_faculties`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1

                                UNION ALL
                               
                                SELECT 'programme' AS permission_type, programmes_programme_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_programmes
                                INNER JOIN `permission_to` ON `users_has_permission_to_programmes`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1

                                UNION ALL

                                SELECT 'intake' AS permission_type, intakes_intake_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_intakes
                                INNER JOIN `permission_to` ON `users_has_permission_to_intakes`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='5'

                                UNION ALL

                                SELECT 'intake_students' AS permission_type, intakes_intake_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_intakes
                                INNER JOIN `permission_to` ON `users_has_permission_to_intakes`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='8'

                                UNION ALL
                                
                                SELECT 'intake_payments' AS permission_type, intakes_intake_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_intakes
                                INNER JOIN `permission_to` ON `users_has_permission_to_intakes`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='10'

                                UNION ALL

                                SELECT 'level' AS permission_type, levels_level_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_levels
                                INNER JOIN `permission_to` ON `users_has_permission_to_levels`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1

                                UNION ALL

                                SELECT 'subject' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='7'

                                UNION ALL

                                SELECT 'lecture_schedule' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='11'

                                UNION ALL

                                SELECT 'pre_Recorded_lectures' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='12'

                                UNION ALL

                                SELECT 'lecture_recording' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='13'

                                UNION ALL
                                 
                                SELECT 'subject_students' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='14'
                                
                                UNION ALL

                                SELECT 'subject_lecturers' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='9'

                                UNION ALL
                                 
                                SELECT 'exams' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='15'

                                 UNION ALL
                                 
                                SELECT 'assignments' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='16'

                                UNION ALL
                                 
                                SELECT 'quiz' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='17'
                                
                                UNION ALL
                                 
                                SELECT 'subject_overview' AS permission_type, subjects_subject_id AS permission_type_related_id, permissions_permission_id AS permission_id
                                FROM users_has_permission_to_subject
                                INNER JOIN `permission_to` ON `users_has_permission_to_subject`.`permission_to_permission_to_id` = `permission_to`.`permission_to_id`
                                WHERE users_user_id = '" . $data["user_id"] . "' AND status_status_id = 1 AND `permission_to`.`permission_type_permission_type_id`='18'
                                "
                            );
                            $userPermissions = [];
                            if ($userPermissions_rs->num_rows > 0) {
                                while ($permission = $userPermissions_rs->fetch_assoc()) {
                                    $userPermissions[] = (object) $permission;
                                }
                            }

                            $userData = [
                                "user_id" => $data["user_id"],
                                "user_fname" => $data["user_fname"],
                                "user_lname" => $data["user_lname"],
                                "user_email" => $data["user_email"],
                                "user_image" => $data["profile_img"],
                                "user_roles_user_role_id" => $data["user_roles_user_role_id"],
                                "user_permissions" => $userPermissions
                            ];

                            SessionUser::startSession($data['user_role'], $userData);
                            setcookie("aic_uni_lms__user_login", "login", time() + 3600, "/");
                            if ($rememberMe) {
                                setcookie("aic_uni_lms__user_email", $email, time() + (86400 * 30), "/", "", true, true);
                                setcookie("aic_uni_lms__user_password", $password, time() + (86400 * 30), "/", "", true, true);
                            }

                            require '../vendor/autoload.php';

                            $agent = new Jenssegers\Agent\Agent();
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);

                            $deviceType = $agent->isDesktop() ? 'Desktop' : ($agent->isPhone() ? 'Mobile' : 'Tablet');
                            $browser = $agent->browser();
                            $deviceName = $agent->device();
                            $os = $agent->platform();
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $loginTime = $dateTime->format('Y-m-d H:i:s');

                            $deviceCheck = Database::search("
                                        SELECT * FROM users_devices 
                                        WHERE users_user_id = '" . $data["user_id"] . "'
                                        AND device_name = '" . $deviceName . "'
                                        AND device_type = '" . $deviceType . "'
                                        AND browser = '" . $browser . "'
                                        AND os = '" . $os . "'
                                    ");

                            if ($deviceCheck->num_rows > 0) {

                                $deviceData = $deviceCheck->fetch_assoc();

                                Database::iud("
                                        UPDATE users_devices 
                                        SET 
                                            last_active = '" . $loginTime . "',
                                            ip_address = '" . $ip . "',
                                            is_active = 1
                                        WHERE users_devices_id = '" . $deviceData["users_devices_id"] . "'
                                    ");
                            } else {

                                $sessionToken = bin2hex(random_bytes(32)); // strong token

                                Database::iud("
                                    INSERT INTO users_devices
                                    (
                                        users_user_id,
                                        device_name,
                                        device_type,
                                        browser,
                                        os,
                                        ip_address,
                                        session_token,
                                        login_time,
                                        last_active,
                                        is_active
                                    ) VALUES (
                                        '" . $data["user_id"] . "',
                                        '" . $deviceName . "',
                                        '" . $deviceType . "',
                                        '" . $browser . "',
                                        '" . $os . "',
                                        '" . $ip . "',
                                        '" . $sessionToken . "',
                                        '" . $loginTime . "',
                                        '" . $loginTime . "',
                                        1
                                    )
                                ");
                            }

                            $status->done =  "Sign In Success";
                        } else {
                            $status->email = "";
                            $status->password = "Your account has been deactivated";
                        }
                    } else {
                        $status->email = "";
                        $status->password = "Incorrect email or password";
                    }
                } else {
                    $status->email = "";
                    $status->password = "Incorrect email or password";
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

echo (json_encode($status));
