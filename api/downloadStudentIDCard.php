<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../vendor/autoload.php';
require_once '../includes/PdfHelper.php';

use setasign\Fpdi\Fpdi;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

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

                    $student = $decoded['id'];
                    $studentIntakeId = $decoded['shiId'];

                    if (empty($student) || $student == 0 || !is_numeric($student)) {
                        $status->error = 'Invalid Student Id';
                    } else {
                        $query = "SELECT `students`.`student_id` AS `reg_id`,
                        `name_with_initials`,
                        `students_has_intakes`.`student_id`,
                        `programme`,`addmission_date`,`nic`,`passport`,`profile_img` FROM `students`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                        INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                        INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                        WHERE `students`.`student_id` = '" . $student . "'";

                        if (!empty($studentIntakeId)) {
                            $query .= " AND `students_has_intakes`.`students_has_intakes_id` = '" . $studentIntakeId . "'";
                        }
                        $isValidStudent = Database::search($query);
                        if ($isValidStudent->num_rows == 0) {
                            $status->error = 'Invalid Student Id';
                        } else {
                            $data = $isValidStudent->fetch_assoc();

                            $studentAddress = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id`='" . $data['reg_id'] . "'");
                            $studentAddressData = $studentAddress->fetch_assoc();

                            $pdf = new Fpdi();
                            $outputFile = $data['student_id'] . ".pdf";

                            $sourceFile = "../assets/uploads/applications/student-id-template.pdf";
                            $pageCount = $pdf->setSourceFile($sourceFile);

                            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

                                $tplId = $pdf->importPage($pageNo);

                                $size = $pdf->getTemplateSize($tplId);

                                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

                                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);


                                if ($pageNo === 1) {
                                    $image = "../assets/imgs/students/default-user.png";

                                    if (!empty($data['profile_img'])) {
                                        $profileImg = Project::getBasePortalUrl() . "/assets/imgs/students/" . $data['profile_img'];
                                        $localImg = "../assets/imgs/students/" . $data['profile_img'];

                                        $headers = @get_headers($profileImg);

                                        if ($headers && strpos($headers[0], '200') !== false) {
                                            $image = $profileImg;
                                        } else if (file_exists($localImg)) {
                                            $image = $localImg;
                                        }
                                    }

                                    PDFHelper::RoundedImage($pdf, $image, 20.2, 27.2, 19.56, 19.56, 1);

                                    $pdf->SetFont('Courier', 'B', 10);

                                    $pdf->SetY(50.5);
                                    $pdf->Cell(0, 5, $data['name_with_initials'], 0, 1, 'C');


                                    $pdf->SetFont('Courier', '', 5);

                                    $pdf->SetXY(25, 58.5);
                                    $pdf->Write(0, $data['student_id']);

                                    $pdf->SetXY(25, 60.8);
                                    $width =  35;

                                    $text = $data['programme'];
                                    if (strlen($text) > 50) {
                                        $text = substr($text, 0, 50) . '...';
                                    }

                                    $pdf->SetXY(25, 60.8);
                                    $pdf->MultiCell(35, 2, $text, 0, 'L');

                                    $pdf->SetXY(25, 66.3);
                                    if (!empty($data['nic'])) {
                                        $pdf->Write(0, $data['nic']);
                                    } else if (!empty($data['passport'])) {
                                        $pdf->Write(0, $data['passport']);
                                    }

                                    $pdf->SetAutoPageBreak(false);
                                    $pdf->SetXY(25, 68.5);
                                    if (!empty($studentAddressData)) {
                                        $address = $studentAddressData['address_line_1'] . " " . $studentAddressData['address_line_2'];
                                        $width =  35;

                                        $pdf->MultiCell($width, 2, $address, 0, 'L');

                                        if (strlen($address) > 50) {
                                            $text = substr($text, 0, 50) . '...';
                                        }

                                        $pdf->SetXY(25, 60.8);
                                        $pdf->MultiCell(35, 2, $text, 0, 'L');
                                    }


                                    $valid_date = new DateTime($data['addmission_date']);
                                    $valid_date->modify('+1 year');
                                    $pdf->SetXY(42.5, 74.5);
                                    $pdf->Cell(30, 5, $valid_date->format('Y/m/d'), 0, 0, 'L');
                                }

                                if ($pageNo === 2) {

                                    $writer = new PngWriter();

                                    $qrCode = new QrCode(
                                        data: $data['student_id'],
                                        encoding: new Encoding('UTF-8'),
                                        errorCorrectionLevel: ErrorCorrectionLevel::Low,
                                        size: 300,
                                        margin: 5,
                                        foregroundColor: new Color(0, 0, 0),
                                        backgroundColor: new Color(255, 255, 255)
                                    );

                                    $result = $writer->write($qrCode);
                                    $qrPath = '../assets/uploads/students-qr/' . $data['student_id'] . '.png';

                                    $result->saveToFile($qrPath);

                                    if (file_exists($qrPath)) {
                                        $pdf->Image($qrPath, 7, 70, 15, 15);

                                        unlink($qrPath);
                                    }
                                }
                            }

                            header('Content-Type: application/pdf');
                            header('Content-Disposition: attachment; filename="' . $outputFile . '"');

                            echo $pdf->Output('', 'S');
                            exit;

                            exit();
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
