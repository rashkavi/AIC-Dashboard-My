<?php

/**
 * Shared student identity-document helpers.
 * (Same config / rules as api/addRegistration.php - you can move addRegistration
 * to this file later and delete its local copies.)
 */

function getStudentDocumentTypeConfig()
{
    return [
        "aic_application"            => ["type_id" => 1,  "folder" => "aic-applications",            "prefix" => "AIC_APPLICATION",            "label" => "AIC Application"],
        "university_application"     => ["type_id" => 2,  "folder" => "university-applications",     "prefix" => "UNIVERSITY_APPLICATION",     "label" => "University Application"],
        "signed_agreement"           => ["type_id" => 3,  "folder" => "signed-agreements",           "prefix" => "SIGNED_AGREEMENT",           "label" => "Signed Agreement"],
        "signed_program_brief"       => ["type_id" => 4,  "folder" => "signed-program-briefs",       "prefix" => "SIGNED_PROGRAM_BRIEF",       "label" => "Signed Program Brief"],
        "passport_nic"               => ["type_id" => 5,  "folder" => "nic-documents",               "prefix" => "PASSPORT_NIC",               "label" => "Passport / NIC"],
        "academic_documents"         => ["type_id" => 6,  "folder" => "academic-documents",          "prefix" => "ACADEMIC_DOCUMENT",          "label" => "Academic Documents"],
        "other_entry_documents"      => ["type_id" => 7,  "folder" => "other-entry-documents",       "prefix" => "OTHER_ENTRY_DOCUMENT",       "label" => "Other Entry Documents"],
        "payment_slip"               => ["type_id" => 8,  "folder" => "payment-slips",               "prefix" => "PAYMENT_SLIP",               "label" => "Payment Slip"],
        "manager_checklist"          => ["type_id" => 9,  "folder" => "manager-checklists",          "prefix" => "MANAGER_CHECKLIST",          "label" => "Manager Checklist"],
        "passport_size_photo"        => ["type_id" => 10, "folder" => "passport-size-photo",         "prefix" => "PASSPORT_SIZE_PHOTO",        "label" => "Passport Size Photo"],
        "discount_approved_document" => ["type_id" => 11, "folder" => "discount-approved-documents", "prefix" => "DISCOUNT_APPROVED_DOCUMENT", "label" => "Discount Approved Document"],
        "signed_fee_structure"       => ["type_id" => 12, "folder" => "signed-fee-structure",        "prefix" => "SIGNED_FEE_STRUCTURE",       "label" => "Signed Fee Structure"],
    ];
}

/** Fields that accept more than one file (input name must be "field[]"). */
function getMultiFileDocumentFields()
{
    return ["academic_documents", "other_entry_documents"];
}

function getDocumentFolderByTypeId($typeId)
{
    foreach (getStudentDocumentTypeConfig() as $cfg) {
        if ((int)$cfg["type_id"] === (int)$typeId) {
            return $cfg["folder"];
        }
    }
    return null;
}

/**
 * Required documents for registering an EXISTING student into a new intake.
 * Same list as addRegistration, except Passport / NIC is only required
 * when the student has none on file (it is a common document).
 */
function getRequiredDocumentsForStudent($studentId)
{
    $required = [
        "payment_slip",
        "signed_fee_structure",
        "discount_approved_document",
        "aic_application",
    ];

    $rs = Database::search("SELECT COUNT(*) AS `c` FROM `students_identity_documents`
        WHERE `students_student_id`='" . (int)$studentId . "' AND `students_identity_documents_type_id`='5'");
    $row = $rs->fetch_assoc();
    if ((int)$row['c'] === 0) {
        $required[] = "passport_nic";
    }

    return $required;
}

function normalizeFilesArray($fileInput)
{
    $files = [];

    if (is_array($fileInput["name"])) {
        foreach ($fileInput["name"] as $index => $name) {
            $files[] = [
                "name"     => $fileInput["name"][$index],
                "type"     => $fileInput["type"][$index],
                "tmp_name" => $fileInput["tmp_name"][$index],
                "error"    => $fileInput["error"][$index],
                "size"     => $fileInput["size"][$index],
            ];
        }
    } else {
        $files[] = $fileInput;
    }

    return $files;
}

function validateStudentDocuments($requiredDocuments)
{
    $maxSize = 8 * 1024 * 1024; // 8MB
    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $validFiles = [];
    $errors = [];
    $uploadedDocuments = [];

    foreach (getStudentDocumentTypeConfig() as $fieldName => $config) {

        if (!isset($_FILES[$fieldName])) {
            continue;
        }

        foreach (normalizeFilesArray($_FILES[$fieldName]) as $file) {

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
                "type_id"    => $config["type_id"],
                "folder"     => $config["folder"],
                "prefix"     => $config["prefix"],
                "file"       => $file,
                "extension"  => $extension,
            ];
        }
    }

    foreach ($requiredDocuments as $requiredDoc) {
        if (!isset($uploadedDocuments[$requiredDoc]) && !isset($errors[$requiredDoc])) {
            $errors[$requiredDoc] = "This document is required";
        }
    }

    return ["files" => $validFiles, "errors" => $errors];
}

/**
 * Moves the files and inserts students_identity_documents rows.
 * Throws RuntimeException if a file cannot be moved (already-moved files are removed).
 */
function uploadStudentDocuments($studentId, $studentHasIntakeId, $studentDocuments)
{
    $baseUploadDir = "../assets/uploads/student-identity-documents/";

    $commonMap = [];
    $typeResult = Database::search("SELECT `students_identity_documents_type_id`, `is_common` FROM `students_identity_documents_type`");
    while ($t = $typeResult->fetch_assoc()) {
        $commonMap[(int)$t['students_identity_documents_type_id']] = (int)$t['is_common'];
    }

    $movedFiles = [];

    try {
        foreach ($studentDocuments["files"] as $doc) {

            $typeId    = (int)$doc["type_id"];
            $uploadDir = $baseUploadDir . $doc["folder"] . "/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = $doc["prefix"] . "_" . uniqid() . "_" . rand(1000, 9999) . "." . $doc["extension"];
            $serverPath  = $uploadDir . $newFileName;

            if (!move_uploaded_file($doc["file"]["tmp_name"], $serverPath)) {
                throw new RuntimeException("Failed to store uploaded file");
            }
            $movedFiles[] = $serverPath;

            // Common docs belong to the student only; other docs are tied to the intake
            $isCommon = isset($commonMap[$typeId]) ? $commonMap[$typeId] : 1;
            $shiValue = $isCommon ? "NULL" : "'" . (int)$studentHasIntakeId . "'";

            Database::iud("
                INSERT INTO `students_identity_documents`
                (`students_student_id`, `students_has_intakes_students_has_intakes_id`, `students_identity_documents_type_id`, `document`, `date_added`)
                VALUES
                ('" . (int)$studentId . "', " . $shiValue . ", '" . $typeId . "', '" . Database::escape($newFileName) . "', NOW())
            ");
        }
    } catch (Throwable $e) {
        foreach ($movedFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $e;
    }
}
