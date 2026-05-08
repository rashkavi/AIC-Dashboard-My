<?php

$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/Project.php';
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    require_once "../includes/connection.php";
                    require_once "../includes/UserPermissionsClass.php";

                    $student = $decoded['student'];

                    if (empty($student) || $student == 0 || !is_numeric($student)) {
                        $status->error = 'Invalid Student Id';
                    } else {
                        $isValidStudent = Database::search("SELECT * FROM `students`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                        WHERE `student_id` = '" . $student . "'");
                        if ($isValidStudent->num_rows == 0) {
                            $status->error = 'Invalid Student Id';
                        } else {
                            $studentData = $isValidStudent->fetch_assoc();

                            $fullname = $studentData['first_name'] . " " . $studentData['last_name'];
                            $email = $studentData['email'];

                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                            Database::iud("UPDATE `login_details` SET `verification` = '" . $token . "' WHERE `login_details_id` = '" . $studentData['login_details_login_details_id'] . "'");

                            $url = "setup-password?token=" . $token;

                            if (!empty($data['verification']) && empty($data['reset_time'])) {
                                $formattedWhatsapp = normalizePhoneNumber($studentData['whatsapp']);

                                $curl = curl_init();
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => 'https://graph.facebook.com/v22.0/1046956908493510/messages',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_POST => true,
                                    CURLOPT_HTTPHEADER => array(
                                        "Authorization: Bearer EAAWijtYusPgBRMOqgf1ZChSWZBhrXDDKn2XZBl3mavZCkZC1mKfvDaZAfrOKTeaOJce5bMB6fJmK3qsvG545CkUJclqxBJc8aDIc2mFI4pGb1shsAW0z35XLCvugiuZCtyWhUerzoMg5lWI1Ky5hbNQs0iZC3NuoTaavKFJ8iLazqvMVPELLZAKG6FgA8kdDDgJG7zwZDZD",
                                        "Content-Type: application/json"
                                    ),
                                    CURLOPT_POSTFIELDS => json_encode([
                                        "messaging_product" => "whatsapp",
                                        "recipient_type" => "individual",
                                        "to" => $formattedWhatsapp,
                                        "type" => "template",
                                        "template" => [
                                            "name" => "welcome_message",
                                            "language" => [
                                                "code" => "en_US"
                                            ],
                                            "components" => [
                                                [
                                                    "type" => "body",
                                                    "parameters" => [
                                                        [
                                                            "type" => "text",
                                                            "text" => $fullname
                                                        ]
                                                    ]
                                                ],
                                                [
                                                    "type" => "button",
                                                    "sub_type" => "url",
                                                    "index" => "0",
                                                    "parameters" => [
                                                        [
                                                            "type" => "text",
                                                            "text" => $url,
                                                        ]
                                                    ]
                                                ]

                                            ]
                                        ]
                                    ])
                                ));
                                $response = curl_exec($curl);
                                curl_close($curl);

                                $curlData = json_decode($response, true);
                                if (isset($curlData['error'])) {
                                    $status->error =  "Resend registration link failed";
                                } else {
                                    $status->done =  "Resend registration link successfully";
                                }
                            } else {
                                require_once("../includes/phpmailer/mail-process.php");
                                require_once("../includes/phpmailer/student-mail-body.php");

                                try {
                                    if (empty($studentData['reset_time'])) {
                                        $url = Project::getBasePortalUrl() . "/setup-password?token=" . $token;
                                        sendMail($email, $fullname, "Verify Email", renderMailBody($url, 1));
                                    } else {
                                        $url = Project::getBasePortalUrl() . "/forgot-password?token=" . $token;
                                        sendMail($email, $fullname, "Forgoten Password", renderMailBody($url, 0));
                                    }
                                    $status->done =  "Email resend successfully!";
                                } catch (Exception $e) {
                                    $status->error = 'Email sending failed.';
                                }
                            }
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

echo (json_encode($status));


function normalizePhoneNumber($number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}
