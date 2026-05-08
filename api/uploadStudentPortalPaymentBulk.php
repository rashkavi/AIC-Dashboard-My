<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!isset($_FILES["file"])) {
                $status->file = "Please select a file";
            } else {
                $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
                if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                    $status->file = "Please select a valid CSV file";
                } else {
                    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                        $status->file = "Please select a valid CSV file";
                    } else {
                        $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                        fgetcsv($csvFile);

                        $approvedStudebtCount = 0;

                        $csvData = [];

                        // Loop through each line of the CSV file
                        while (($line = fgetcsv($csvFile)) !== FALSE) {
                            if (count(array_filter($line)) != 0) {
                                $csvData[] = $line; // Add each line to the array
                            }
                        }
                        fclose($csvFile);
                        if (count($csvData) == 0) {
                            $status->file = "Please select a valid CSV file";
                        } else if (count($csvData) > 100) {
                            $status->file = "Only 100 students can be uploaded at a time";
                        }

                        if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                            $rowNumber = 1;

                            $linesErrors = [];

                            foreach ($csvData as $data) {

                                $studentId = $data[0];

                                $lineErrors = [];

                                if (empty($studentId)) {
                                    $lineErrors[] =  "Row " . $rowNumber . ": student id cannot be empty";
                                } else {
                                    $isValidStudent = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE `student_id`='" . $studentId . "'");
                                    $row = $isValidStudent->fetch_assoc();
                                    if ($row['count'] == 0) {
                                        $lineErrors[] =  "Row " . $rowNumber . ": Invalid student id";
                                    } else {
                                        $studentData = $isValidStudent->fetch_assoc();
                                        $lastPortalPayment = Database::search("SELECT * FROM `portal_payment` 
                                        WHERE `students_student_id`='" . $studentId . "' AND `portal_payment_status_portal_payment_status_id` in (1,2) 
                                        ORDER BY `portal_payment_id` DESC LIMIT 1");
                                        if ($lastPortalPayment->num_rows > 0) {
                                            $lastPortalPaymentData = $lastPortalPayment->fetch_assoc();
                                            if ($lastPortalPaymentData["portal_payment_status_portal_payment_status_id"] == 1) {
                                                $lineErrors[] = "Row " . $rowNumber . ": Last portal payment not completed";
                                            }
                                        }
                                    }
                                }

                                $rowNumber++;
                                if (count($lineErrors) > 0) {
                                    $linesErrors[] = $lineErrors;
                                }
                            }
                            if (!empty($linesErrors)) {
                                $status->dataErrors = $linesErrors;
                            } else {

                                foreach ($csvData as $data) {

                                    $studentId = $data[0];

                                    $lastPortalPayment = Database::search("SELECT * FROM `portal_payment` 
                                        WHERE `students_student_id`='" . $studentId . "'
                                        ORDER BY `portal_payment_id` DESC LIMIT 1");

                                    try {
                                        if ($lastPortalPayment->num_rows == 0) {
                                            $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                            $newLastAccessTime = clone $dateTime;
                                            $newLastAccessTime->modify('+30 day');

                                            $payment_insert_columns = "`students_student_id`,`portal_payment_plan_portal_payment_plan_id`,`amount`,`discount`,`date_paid`, `from`, `to`, `portal_payment_status_portal_payment_status_id`,`payment_method_payment_method_id`,`discount_type_discount_type_id`";
                                            $payment_insert_values = "'" . $studentId . "','2', '200.00', '0', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $newLastAccessTime->format("Y-m-d H:i:s") . "', '2', '1','1'";

                                            Database::iud("INSERT INTO `portal_payment`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");
                                            $paymentId = Database::$connection->insert_id;

                                            $paymnet_log_columns = "`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`";
                                            $payment_log_values = "'" . $paymentId . "','" . $sessionUserObject['user_id'] . "', '" . $dateTime->format("Y-m-d H:i:s") . "', 'Add  Portal Payment'";
                                            Database::iud("INSERT INTO `portal_payment_logs` (" . $paymnet_log_columns . ") VALUES (" . $payment_log_values . ")");
                                        } else {
                                            $lastPortalPaymentData = $lastPortalPayment->fetch_assoc();
                                            if ($lastPortalPaymentData["portal_payment_status_portal_payment_status_id"] == 1) {
                                                $status->warning = "Last portal payment not completed";
                                            } else {
                                                $lastAccessTime = new DateTime($lastPortalPaymentData["to"]);
                                                $lastAccessTime->modify('+1 second');
                                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                                $newLastAccessTime = clone $lastAccessTime;
                                                $newLastAccessTime->modify('+30 day');

                                                if ($lastPortalPaymentData["portal_payment_status_portal_payment_status_id"] == 3) {
                                                    $lastAccessTime = new DateTime($lastPortalPaymentData["from"]);
                                                    $newLastAccessTime = new DateTime($lastPortalPaymentData["to"]);
                                                }

                                                $payment_insert_columns = "`students_student_id`,`portal_payment_plan_portal_payment_plan_id`,`amount`,`discount`,`date_paid`, `from`, `to`, `portal_payment_status_portal_payment_status_id`,`payment_method_payment_method_id`,`discount_type_discount_type_id`";
                                                $payment_insert_values = "'" . $studentId . "','2', '200.00', '0', '" . $dateTime->format("Y-m-d H:i:s") . "', '" . $lastAccessTime->format("Y-m-d H:i:s") . "', '" . $newLastAccessTime->format("Y-m-d H:i:s") . "', '2', '1','1'";

                                                Database::iud("INSERT INTO `portal_payment`(" . $payment_insert_columns . ") VALUES(" . $payment_insert_values . ")");
                                                $paymentId = Database::$connection->insert_id;

                                                $paymnet_log_columns = "`portal_payment_portal_payment_id`,`users_user_id`,`date_updated`,`note`";
                                                $payment_log_values = "'" . $paymentId . "','" . $sessionUserObject['user_id'] . "', '" . $dateTime->format("Y-m-d H:i:s") . "', 'Add  Portal Payment'";
                                                Database::iud("INSERT INTO `portal_payment_logs` (" . $paymnet_log_columns . ") VALUES (" . $payment_log_values . ")");
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $status->error = $e->getMessage();
                                    }
                                }

                                $status->done = "Successfully added portal payments";
                            }
                        }
                    }
                }
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
