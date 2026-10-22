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

            $id = isset($_POST['id']) ? trim($_POST['id']) : '';

            if (empty($id)) {
                $status->error = "Invalid student Id";
            } else {
                $isHasId =  Database::search("SELECT *,`students`.`student_id` AS `sId` FROM `students` 
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                INNER JOIN `students_has_intakes` ON `students`.`student_id`=`students_has_intakes`.`students_student_id`
                         WHERE `students_has_intakes`.`students_has_intakes_id`='" . $id . "'");

                if ($isHasId->num_rows == 0) {
                    $status->error = "Invalid student Id";
                } else {

                    $student = $isHasId->fetch_assoc();

                    $studentDocuments = validateStudentDocuments($id);

                    foreach ($studentDocuments["errors"] as $key => $message) {
                        $status->$key = $message;
                    }

                    if (count((array)$status) > 0) {
                        echo json_encode($status);
                        exit;
                    }
                    $allFieldsPresent = true;

                    if ($allFieldsPresent) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $resetTime = $dateTime->format('Y-m-d H:i:s');



                        $docFolderMap = [
                            1 => "aic-applications",
                            2 => "university-applications",
                            3 => "signed-agreements",
                            4 => "signed-program-briefs",
                            5 => "passport-nic-documents",
                            6 => "academic-documents",
                            7 => "other-entry-documents",
                            8 => "payment-slips",
                            9 => "manager-checklists",
                            10 => "passport-size-photos",
                            11 => "discount-approved-documents"
                        ];

                        $docRs = Database::search("
                        SELECT 
                            `students_identity_document_id`,
                            `students_identity_documents_type_id`,
                            `document`
                        FROM `students_identity_documents`
                        WHERE `students_identity_documents_type_id` = '8'
                          AND `students_has_intakes_students_has_intakes_id` = '" . $id . "'
                        LIMIT 1
                    ");

                        if ($docRs->num_rows > 0) {
                            $doc = $docRs->fetch_assoc();

                            $typeId = (int)$doc["students_identity_documents_type_id"];
                            $fileName = basename($doc["document"]);

                            if (isset($docFolderMap[$typeId])) {
                                $filePath = "../assets/uploads/student-identity-documents/" . $docFolderMap[$typeId] . "/" . $fileName;

                                if (is_file($filePath)) {
                                    unlink($filePath);
                                }
                            }

                            Database::iud("
                        DELETE FROM `students_identity_documents`
                        WHERE `students_identity_document_id` = '" . $doc["students_identity_document_id"] . "'
                          AND `students_has_intakes_students_has_intakes_id` = '" . $id . "'
                    ");
                        }

                        uploadStudentDocuments($id, $studentDocuments);

                        Database::iud("UPDATE `students_has_intakes` SET `lead_student_status_lead_student_status_id` = '1' WHERE `students_has_intakes_id` = '" . $id . "'");

                        Database::iud("INSERT INTO `students_logs` 
                            (`students_has_intakes_students_has_intakes_id`, `date_updated`, `users_user_id`,`log`,`lead_student_status_lead_student_status_id`)
                             VALUES ('" . $id . "','" . $resetTime . "', '" . $sessionUserObject['user_id'] . "','Re-Upload Registration Payment Slip','1')");

                        $status->done =  "Registration Payment Slip Uploaded Successfully";
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


function validateStudentDocuments($id)
{
    $maxSize = 8 * 1024 * 1024; // 8MB same as JS

    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $requiredDocuments = [
        "payment_slip",
    ];

    $documentTypes = getStudentDocumentTypeConfig();

    $validFiles = [];
    $errors = [];
    $uploadedDocuments = [];

    foreach ($documentTypes as $fieldName => $config) {

        if (!isset($_FILES[$fieldName])) {
            continue;
        }

        $files = normalizeFilesArray($_FILES[$fieldName]);

        foreach ($files as $file) {

            if ($file["error"] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $errors[$fieldName] = "Upload error occurred";
                continue;
            }

            if ($file["size"] > $maxSize) {
                $errors[$fieldName] = "File size must be less than 8MB";
                continue;
            }

            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $errors[$fieldName] = "Invalid file type";
                continue;
            }

            $uploadedDocuments[$fieldName] = true;

            $validFiles[] = [
                "field_name" => $fieldName,
                "type_id" => $config["type_id"],
                "folder" => $config["folder"],
                "prefix" => $config["prefix"],
                "file" => $file,
                "extension" => $extension
            ];
        }
    }

    foreach ($requiredDocuments as $requiredDoc) {
        if (!isset($uploadedDocuments[$requiredDoc])) {
            $errors[$requiredDoc] = "This document is required";
        }
    }

    return [
        "files" => $validFiles,
        "errors" => $errors
    ];
}
function getStudentDocumentTypeConfig()
{
    return [
        "aic_application" => [
            "type_id" => 1,
            "folder" => "aic-applications",
            "prefix" => "AIC_APPLICATION"
        ],
        "university_application" => [
            "type_id" => 2,
            "folder" => "university-applications",
            "prefix" => "UNIVERSITY_APPLICATION"
        ],
        "signed_agreement" => [
            "type_id" => 3,
            "folder" => "signed-agreements",
            "prefix" => "SIGNED_AGREEMENT"
        ],
        "signed_program_brief" => [
            "type_id" => 4,
            "folder" => "signed-program-briefs",
            "prefix" => "SIGNED_PROGRAM_BRIEF"
        ],
        "passport_nic" => [
            "type_id" => 5,
            "folder" => "nic-documents",
            "prefix" => "PASSPORT_NIC"
        ],
        "academic_documents" => [
            "type_id" => 6,
            "folder" => "academic-documents",
            "prefix" => "ACADEMIC_DOCUMENT"
        ],
        "other_entry_documents" => [
            "type_id" => 7,
            "folder" => "other-entry-documents",
            "prefix" => "OTHER_ENTRY_DOCUMENT"
        ],
        "payment_slip" => [
            "type_id" => 8,
            "folder" => "payment-slips",
            "prefix" => "PAYMENT_SLIP"
        ],
        "manager_checklist" => [
            "type_id" => 9,
            "folder" => "manager-checklists",
            "prefix" => "MANAGER_CHECKLIST"
        ],
        "passport_size_photo" => [
            "type_id" => 10,
            "folder" => "passport-size-photo",
            "prefix" => "PASSPORT_SIZE_PHOTO"
        ],
        "discount_approved_document" => [
            "type_id" => 11,
            "folder" => "discount-approved-documents",
            "prefix" => "DISCOUNT_APPROVED_DOCUMENT"
        ]
    ];
}

function normalizeFilesArray($fileInput)
{
    $files = [];

    if (is_array($fileInput["name"])) {
        foreach ($fileInput["name"] as $index => $name) {
            $files[] = [
                "name" => $fileInput["name"][$index],
                "type" => $fileInput["type"][$index],
                "tmp_name" => $fileInput["tmp_name"][$index],
                "error" => $fileInput["error"][$index],
                "size" => $fileInput["size"][$index],
            ];
        }
    } else {
        $files[] = $fileInput;
    }

    return $files;
}

function uploadStudentDocuments($studentId, $studentDocuments)
{
    $baseUploadDir = "../assets/uploads/student-identity-documents/";
    $baseDbPath = "assets/uploads/student-identity-documents/";

    foreach ($studentDocuments["files"] as $doc) {

        $typeId = $doc["type_id"];
        $folder = $doc["folder"];
        $prefix = $doc["prefix"];
        $file = $doc["file"];
        $extension = $doc["extension"];

        $uploadDir = $baseUploadDir . $folder . "/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = $prefix . "_" . uniqid() . "_" . rand(1000, 9999) . "." . $extension;

        $serverPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file["tmp_name"], $serverPath)) {

            $dbPath = $newFileName;

            Database::iud("
                INSERT INTO `students_identity_documents`
                (
                    `students_has_intakes_students_has_intakes_id`,
                    `students_identity_documents_type_id`,
                    `document`,
                    `date_added`
                )
                VALUES
                (
                    '" . $studentId . "',
                    '" . $typeId . "',
                    '" . $dbPath . "',
                    NOW()
                )
            ");
        }
    }
}
